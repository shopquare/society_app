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
import android.os.PowerManager
import android.os.Vibrator
import android.os.VibratorManager
import android.os.VibrationEffect
import android.provider.Settings
import android.util.Log
import android.webkit.CookieManager
import android.webkit.WebSettings
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
import java.util.concurrent.TimeUnit
import java.util.regex.Pattern
import javax.crypto.Cipher
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec

object GateAlertManager {
    private const val TAG = "GateAlertManager"
    const val CHANNEL_ID = "societyhub_gate_alerts"
    const val NOTIFICATION_ID = 9001
    const val BASE_URL = "https://society.infy.click"

    private var mediaPlayer: MediaPlayer? = null
    private var toneGenerator: ToneGenerator? = null
    private var vibrator: Vibrator? = null
    private var wakeLock: PowerManager.WakeLock? = null
    private val handler = Handler(Looper.getMainLooper())
    private var timeoutRunnable: Runnable? = null
    private var toneLoopRunnable: Runnable? = null

    val httpClient = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(10, TimeUnit.SECONDS)
        .build()

    var currentVisitorId: Long = 0L
        private set
    var currentActionToken: String? = null
        private set

    /**
     * Resolves the default browser User-Agent string
     */
    fun getUserAgent(context: Context): String {
        return try {
            WebSettings.getDefaultUserAgent(context)
        } catch (e: Exception) {
            "Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
        }
    }

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
                "SecureGate Gate Alerts",
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
     * 1. Turns screen ON via WakeLock
     * 2. Starts continuous looping alarm sound on USAGE_ALARM stream
     * 3. Vibrates continuously in repeating waveform
     * 4. Displays full-screen activity over lockscreen and above all apps
     * 5. Shows heads-up notification with one-tap Approve/Deny buttons
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

        // 1. Wake screen if off
        try {
            val pm = context.getSystemService(Context.POWER_SERVICE) as PowerManager
            wakeLock?.release()
            @Suppress("DEPRECATION")
            wakeLock = pm.newWakeLock(
                PowerManager.SCREEN_BRIGHT_WAKE_LOCK or PowerManager.ACQUIRE_CAUSES_WAKEUP,
                "SocietyHub:GateAlertWakeLock"
            )
            wakeLock?.acquire(60000)
        } catch (e: Exception) {
            Log.e(TAG, "WakeLock acquire error: ${e.message}")
        }

        // 2. Start continuous audio playback and vibration
        startAlarm(context)

        // 3. Prepare Intent for Full-Screen Alert Activity
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

        // 4. Notification Action: APPROVE
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

        // 5. Notification Action: DENY
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

        // 6. Build High-Priority Heads-Up / Full-Screen Notification
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

        // 7. Launch activity directly if app has overlay permission or screen interactive
        try {
            context.startActivity(fullScreenIntent)
        } catch (e: Exception) {
            Log.d(TAG, "Direct launch handled by fullScreenIntent: ${e.message}")
        }

        // 8. Auto-timeout after 60 seconds
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
                vibrator?.vibrate(VibrationEffect.createWaveform(pattern, 0))
            } else {
                @Suppress("DEPRECATION")
                vibrator?.vibrate(pattern, 0)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Vibrator start failed: ${e.message}")
        }
    }

    /**
     * Stops audio, vibration, wakeLock, and notification
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

        try {
            wakeLock?.let {
                if (it.isHeld) {
                    it.release()
                }
            }
            wakeLock = null
        } catch (e: Exception) {
            Log.e(TAG, "WakeLock release error: ${e.message}")
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
     * Solves InfinityFree aes.js challenge dynamically if encountered
     */
    fun solveInfinityFreeChallenge(html: String): String? {
        val p = Pattern.compile("toNumbers\\(\"([a-f0-9]+)\"\\).*?toNumbers\\(\"([a-f0-9]+)\"\\).*?toNumbers\\(\"([a-f0-9]+)\"\\)", Pattern.DOTALL)
        val m = p.matcher(html)
        if (m.find()) {
            try {
                val aHex = m.group(1) ?: return null
                val bHex = m.group(2) ?: return null
                val cHex = m.group(3) ?: return null

                val key = hexStringToByteArray(aHex)
                val iv = hexStringToByteArray(bHex)
                val cipherText = hexStringToByteArray(cHex)

                val cipher = Cipher.getInstance("AES/CBC/NoPadding")
                val keySpec = SecretKeySpec(key, "AES")
                val ivSpec = IvParameterSpec(iv)
                cipher.init(Cipher.DECRYPT_MODE, keySpec, ivSpec)
                val decrypted = cipher.doFinal(cipherText)

                return byteArrayToHexString(decrypted)
            } catch (e: Exception) {
                Log.e(TAG, "Failed solving aes challenge: ${e.message}")
            }
        }
        return null
    }

    private fun hexStringToByteArray(s: String): ByteArray {
        val len = s.length
        val data = ByteArray(len / 2)
        var i = 0
        while (i < len) {
            data[i / 2] = ((Character.digit(s[i], 16) shl 4) + Character.digit(s[i + 1], 16)).toByte()
            i += 2
        }
        return data
    }

    private fun byteArrayToHexString(bytes: ByteArray): String {
        val sb = java.lang.StringBuilder()
        for (b in bytes) {
            sb.append(String.format("%02x", b))
        }
        return sb.toString()
    }

    /**
     * Asynchronously sends the resident's decision (Approve or Deny)
     */
    fun submitApproval(
        context: Context,
        visitorId: Long,
        status: String,
        actionToken: String? = null,
        callback: (Boolean, String) -> Unit = { _, _ -> }
    ) {
        stopAlarm(context)

        val resolvedToken = actionToken ?: currentActionToken ?: ""
        val normalizedAction = if (status.equals("Entered", ignoreCase = true) || status.equals("Approve", ignoreCase = true)) "Approve" else "Deny"
        val normalizedStatus = if (normalizedAction == "Approve") "Entered" else "Denied"

        // Layer 1: If MainActivity is alive with WebView, execute in web session directly
        MainActivity.instance?.let { mainAct ->
            mainAct.runOnUiThread {
                try {
                    mainAct.executeApprovalInWeb(visitorId, normalizedAction)
                } catch (e: Exception) {
                    Log.d(TAG, "WebView executeApproval error: ${e.message}")
                }
            }
        }

        // Layer 2: Also send via OkHttp with cookies, User-Agent, and anti-bot challenge support
        val jsonPayload = JSONObject().apply {
            put("visitor_id", visitorId)
            put("action", normalizedAction)
            put("status", normalizedStatus)
            if (resolvedToken.isNotEmpty()) {
                put("action_token", resolvedToken)
            }
        }.toString()

        val userAgent = getUserAgent(context)
        val mediaType = "application/json; charset=utf-8".toMediaType()

        fun performPost(cookiesToSend: String, retryOnChallenge: Boolean) {
            val requestBuilder = Request.Builder()
                .url("$BASE_URL/api/resident_handle_visitor_approval.php")
                .post(jsonPayload.toRequestBody(mediaType))
                .header("Content-Type", "application/json")
                .header("User-Agent", userAgent)
                .header("X-Requested-With", "com.shopquare.societyhub")

            if (cookiesToSend.isNotEmpty()) {
                requestBuilder.header("Cookie", cookiesToSend)
            }

            httpClient.newCall(requestBuilder.build()).enqueue(object : Callback {
                override fun onFailure(call: Call, e: IOException) {
                    Log.e(TAG, "Approval network error: ${e.message}")
                    handler.post { callback(false, e.message ?: "Network error") }
                }

                override fun onResponse(call: Call, response: Response) {
                    val bodyStr = response.body?.string().orEmpty()

                    // Check if intercepted by aes.js
                    if (bodyStr.contains("toNumbers") && bodyStr.contains("slowAES") && retryOnChallenge) {
                        val testCookieVal = solveInfinityFreeChallenge(bodyStr)
                        if (!testCookieVal.isNullOrEmpty()) {
                            val newCookieStr = if (cookiesToSend.isEmpty()) "__test=$testCookieVal" else "$cookiesToSend; __test=$testCookieVal"
                            try {
                                CookieManager.getInstance().setCookie(BASE_URL, "__test=$testCookieVal")
                                CookieManager.getInstance().flush()
                            } catch (e: Exception) {}
                            performPost(newCookieStr, false)
                            return
                        }
                    }

                    val isSuccess = response.isSuccessful && (bodyStr.contains("\"status\":\"success\"") || bodyStr.contains("\"success\":true"))
                    handler.post { callback(isSuccess, if (isSuccess) "Success" else bodyStr) }
                }
            })
        }

        val currentCookies = try {
            CookieManager.getInstance().getCookie(BASE_URL).orEmpty()
        } catch (e: Exception) {
            ""
        }
        performPost(currentCookies, true)
    }
}
