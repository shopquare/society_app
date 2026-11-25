package com.shopquare.societyhub

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.media.ToneGenerator
import android.net.Uri
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.Vibrator
import android.os.VibratorManager
import android.os.VibrationEffect
import android.util.Log
import android.webkit.CookieManager
import androidx.core.app.NotificationCompat
import okhttp3.Call
import okhttp3.Callback
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import org.json.JSONObject
import java.io.IOException

object GateAlertManager {
    private const val TAG = "GateAlertManager"
    const val CHANNEL_ID = "societyhub_gate_alerts"
    const val NOTIFICATION_ID = 9001
    const val BASE_URL = "https://society.infy.click"

    private var mediaPlayer: MediaPlayer? = null
    private var toneGenerator: ToneGenerator? = null
    private var vibrator: Vibrator? = null
    private val httpClient = OkHttpClient()
    private val handler = Handler(Looper.getMainLooper())
    private var timeoutRunnable: Runnable? = null
    private var toneLoopRunnable: Runnable? = null

    var currentVisitorId: Long = 0L
        private set
    var currentActionToken: String? = null
        private set

    /**
     * Initializes the loud high-priority notification channel
     */
    fun createNotificationChannel(context: Context) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val soundUri: Uri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

            val audioAttributes = AudioAttributes.Builder()
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .setUsage(AudioAttributes.USAGE_ALARM)
                .build()

            val channel = NotificationChannel(
                CHANNEL_ID,
                "Gate Visitor Alerts (Urgent & Loud)",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Incoming visitor approvals at security gate"
                setSound(soundUri, audioAttributes)
                enableVibration(true)
                vibrationPattern = longArrayOf(0, 800, 400, 800, 400, 800)
                lockscreenVisibility = NotificationCompat.VISIBILITY_PUBLIC
                setBypassDnd(true)
            }

            val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }

    /**
     * Triggers the full incoming gate alert:
     * 1. Starts continuous looping alarm sound on USAGE_ALARM stream
     * 2. Vibrates continuously
     * 3. Launches Full-Screen Intent directly on lock screen
     * 4. Shows heads-up notification with one-tap Approve/Deny buttons
     * 5. Auto-times out after 60 seconds if unhandled
     */
    fun triggerGateAlert(
        context: Context,
        visitorId: Long,
        visitorName: String,
        purpose: String,
        unitNumber: String,
        photoUrl: String?,
        actionToken: String? = null
    ) {
        currentVisitorId = visitorId
        currentActionToken = actionToken

        createNotificationChannel(context)

        // 1. Start continuous audio playback and vibration
        startAlarm(context)

        // 2. Prepare Intent for Full-Screen Alert Activity
        val fullScreenIntent = Intent(context, GateAlertActivity::class.java).apply {
            putExtra("VISITOR_ID", visitorId)
            putExtra("VISITOR_NAME", visitorName)
            putExtra("PURPOSE", purpose)
            putExtra("UNIT_NUMBER", unitNumber)
            putExtra("PHOTO_URL", photoUrl)
            putExtra("ACTION_TOKEN", actionToken)
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
        }

        val fullScreenPendingIntent = PendingIntent.getActivity(
            context,
            visitorId.toInt(),
            fullScreenIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0)
        )

        // 3. Prepare Notification Action: APPROVE
        val approveIntent = Intent(context, NotificationActionReceiver::class.java).apply {
            action = "com.shopquare.societyhub.ACTION_APPROVE_VISITOR"
            putExtra("VISITOR_ID", visitorId)
            putExtra("ACTION_TOKEN", actionToken)
        }
        val approvePendingIntent = PendingIntent.getBroadcast(
            context,
            (visitorId * 10 + 1).toInt(),
            approveIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0)
        )

        // 4. Prepare Notification Action: DENY
        val denyIntent = Intent(context, NotificationActionReceiver::class.java).apply {
            action = "com.shopquare.societyhub.ACTION_DENY_VISITOR"
            putExtra("VISITOR_ID", visitorId)
            putExtra("ACTION_TOKEN", actionToken)
        }
        val denyPendingIntent = PendingIntent.getBroadcast(
            context,
            (visitorId * 10 + 2).toInt(),
            denyIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0)
        )

        // 5. Build Heads-Up / Full-Screen Notification
        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle("🚨 VISITOR AT GATE: $visitorName")
            .setContentText("Seeking entry for Flat $unitNumber ($purpose)")
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setAutoCancel(true)
            .setOngoing(true)
            .setFullScreenIntent(fullScreenPendingIntent, true)
            .setContentIntent(fullScreenPendingIntent)
            .addAction(android.R.drawable.checkbox_on_background, "APPROVE", approvePendingIntent)
            .addAction(android.R.drawable.ic_delete, "DENY", denyPendingIntent)
            .build()

        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(NOTIFICATION_ID, notification)

        // 6. Launch activity directly to display incoming call screen over lockscreen
        try {
            context.startActivity(fullScreenIntent)
        } catch (e: Exception) {
            Log.e(TAG, "Direct activity launch failed: ${e.message}")
        }

        // 7. Auto-timeout after 60 seconds
        timeoutRunnable?.let { handler.removeCallbacks(it) }
        timeoutRunnable = Runnable {
            stopAlarm(context)
        }
        handler.postDelayed(timeoutRunnable!!, 60000)
    }

    /**
     * Starts continuous looping alarm sound and vibration
     */
    private fun startAlarm(context: Context) {
        stopAlarm(context)

        var audioStarted = false

        // Attempt 1: Looping MediaPlayer with system Ringtone/Alarm
        try {
            val alertUri: Uri? = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)

            if (alertUri != null) {
                mediaPlayer = MediaPlayer().apply {
                    setDataSource(context, alertUri)
                    setAudioAttributes(
                        AudioAttributes.Builder()
                            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                            .setUsage(AudioAttributes.USAGE_ALARM)
                            .build()
                    )
                    isLooping = true
                    prepare()
                    start()
                }
                audioStarted = true
            }
        } catch (e: Exception) {
            Log.e(TAG, "MediaPlayer start failed, using ToneGenerator fallback: ${e.message}")
        }

        // Attempt 2: Fallback ToneGenerator beep loop if MediaPlayer failed
        if (!audioStarted) {
            try {
                toneGenerator = ToneGenerator(AudioManager.STREAM_ALARM, 100)
                toneLoopRunnable = object : Runnable {
                    override fun run() {
                        try {
                            toneGenerator?.startTone(ToneGenerator.TONE_CDMA_HIGH_L, 1000)
                            handler.postDelayed(this, 1500)
                        } catch (e: Exception) {
                            Log.e(TAG, "ToneGenerator error: ${e.message}")
                        }
                    }
                }
                handler.post(toneLoopRunnable!!)
            } catch (e: Exception) {
                Log.e(TAG, "ToneGenerator init failed: ${e.message}")
            }
        }

        // Continuous vibration
        try {
            vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val vm = context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as VibratorManager
                vm.defaultVibrator
            } else {
                @Suppress("DEPRECATION")
                context.getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
            }

            val pattern = longArrayOf(0, 1000, 600, 1000, 600, 1000)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                vibrator?.vibrate(VibrationEffect.createWaveform(pattern, 0)) // 0 = repeat indefinitely
            } else {
                @Suppress("DEPRECATION")
                vibrator?.vibrate(pattern, 0)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Vibrator start failed: ${e.message}")
        }
    }

    /**
     * Immediately stops audio playback, vibration, and removes timeout handlers
     */
    fun stopAlarm(context: Context? = null) {
        try {
            mediaPlayer?.let {
                if (it.isPlaying) {
                    it.stop()
                }
                it.release()
            }
            mediaPlayer = null
        } catch (e: Exception) {
            Log.e(TAG, "MediaPlayer stop failed: ${e.message}")
        }

        toneLoopRunnable?.let {
            handler.removeCallbacks(it)
            toneLoopRunnable = null
        }

        try {
            toneGenerator?.release()
            toneGenerator = null
        } catch (e: Exception) {
            Log.e(TAG, "ToneGenerator stop failed: ${e.message}")
        }

        try {
            vibrator?.cancel()
            vibrator = null
        } catch (e: Exception) {
            Log.e(TAG, "Vibrator stop failed: ${e.message}")
        }

        timeoutRunnable?.let {
            handler.removeCallbacks(it)
            timeoutRunnable = null
        }

        context?.let {
            val nm = it.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            nm.cancel(NOTIFICATION_ID)
        }
    }

    /**
     * Asynchronously sends the resident's decision (Approve or Deny) to the server
     */
    fun submitApproval(
        context: Context,
        visitorId: Long,
        status: String, // 'Entered' or 'Denied'
        actionToken: String? = null,
        callback: (Boolean, String) -> Unit = { _, _ -> }
    ) {
        stopAlarm(context)

        val resolvedToken = actionToken ?: currentActionToken ?: ""
        val normalizedAction = if (status.equals("Entered", ignoreCase = true) || status.equals("Approve", ignoreCase = true)) "Approve" else "Deny"
        val normalizedStatus = if (normalizedAction == "Approve") "Entered" else "Denied"

        val jsonPayload = JSONObject().apply {
            put("visitor_id", visitorId)
            put("action", normalizedAction)
            put("status", normalizedStatus)
            if (resolvedToken.isNotEmpty()) {
                put("action_token", resolvedToken)
            }
        }.toString()

        val mediaType = "application/json; charset=utf-8".toMediaType()
        val requestBody = jsonPayload.toRequestBody(mediaType)

        // Retrieve current cookies from WebView
        val cookies = try {
            CookieManager.getInstance().getCookie(BASE_URL).orEmpty()
        } catch (e: Exception) {
            ""
        }

        val requestBuilder = Request.Builder()
            .url("$BASE_URL/api/resident_handle_visitor_approval.php")
            .post(requestBody)
            .header("Content-Type", "application/json")
            .header("X-Requested-With", "com.shopquare.societyhub")

        if (cookies.isNotEmpty()) {
            requestBuilder.header("Cookie", cookies)
        }

        val request = requestBuilder.build()

        httpClient.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                Log.e(TAG, "Approval network failed: ${e.message}")
                handler.post { callback(false, e.message ?: "Network error") }
            }

            override fun onResponse(call: Call, response: Response) {
                val bodyStr = response.body?.string().orEmpty()
                val isSuccess = response.isSuccessful && !bodyStr.contains("\"status\":\"error\"")
                handler.post { callback(isSuccess, bodyStr) }
            }
        })
    }
}
