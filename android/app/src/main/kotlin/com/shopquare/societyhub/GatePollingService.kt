package com.shopquare.societyhub

import android.app.AlarmManager
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.os.SystemClock
import android.util.Log
import android.webkit.CookieManager
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class GatePollingService : Service() {

    private val serviceJob = Job()
    private val scope = CoroutineScope(Dispatchers.IO + serviceJob)
    private val handler = Handler(Looper.getMainLooper())
    private val httpClient = OkHttpClient.Builder()
        .connectTimeout(8, TimeUnit.SECONDS)
        .readTimeout(10, TimeUnit.SECONDS)
        .build()

    private var lastAlertedVisitorId: Long = 0L

    companion object {
        private const val TAG = "GatePollingService"
        private const val CHANNEL_ID = "societyhub_background_service"
        private const val SERVICE_NOTIFICATION_ID = 8001
        private const val BASE_URL = "https://society.infy.click"

        fun start(context: Context) {
            val intent = Intent(context, GatePollingService::class.java)
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(intent)
                } else {
                    context.startService(intent)
                }
            } catch (e: Exception) {
                Log.e(TAG, "Service start error: ${e.message}")
            }
        }

        fun stop(context: Context) {
            val intent = Intent(context, GatePollingService::class.java)
            context.stopService(intent)
        }
    }

    override fun onCreate() {
        super.onCreate()
        createServiceNotificationChannel()
        startForegroundWithNotification()
        startPollingLoop()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        return START_STICKY
    }

    override fun onTaskRemoved(rootIntent: Intent?) {
        super.onTaskRemoved(rootIntent)
        Log.d(TAG, "Task removed, scheduling service restart...")
        val restartIntent = Intent(applicationContext, GatePollingService::class.java).apply {
            setPackage(packageName)
        }
        val restartPendingIntent = PendingIntent.getService(
            applicationContext,
            101,
            restartIntent,
            PendingIntent.FLAG_ONE_SHOT or (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) PendingIntent.FLAG_IMMUTABLE else 0)
        )
        val alarmManager = applicationContext.getSystemService(Context.ALARM_SERVICE) as? AlarmManager
        alarmManager?.set(
            AlarmManager.ELAPSED_REALTIME,
            SystemClock.elapsedRealtime() + 1000,
            restartPendingIntent
        )
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onDestroy() {
        super.onDestroy()
        serviceJob.cancel()
    }

    private fun createServiceNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "SocietyHub Gate Monitor",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Monitors security gate for incoming visitor approvals"
                setShowBadge(false)
            }
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }

    private fun startForegroundWithNotification() {
        val notification: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle("Gate Alert Active")
            .setContentText("Listening for incoming visitor approvals at gate...")
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setOngoing(true)
            .build()

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            ServiceCompat.startForeground(
                this,
                SERVICE_NOTIFICATION_ID,
                notification,
                ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC
            )
        } else {
            startForeground(SERVICE_NOTIFICATION_ID, notification)
        }
    }

    private fun startPollingLoop() {
        scope.launch {
            while (isActive) {
                try {
                    pollPendingVisitors()
                } catch (e: Exception) {
                    Log.d(TAG, "Polling loop error: ${e.message}")
                }
                delay(3500)
            }
        }
    }

    private fun pollPendingVisitors() {
        var cookies = try {
            CookieManager.getInstance().getCookie(BASE_URL).orEmpty()
        } catch (e: Exception) {
            ""
        }

        if (cookies.isEmpty() || !cookies.contains("PHPSESSID")) {
            return
        }

        val userAgent = GateAlertManager.getUserAgent(this)

        val request = Request.Builder()
            .url("$BASE_URL/api/resident_get_pending_visitors.php")
            .header("User-Agent", userAgent)
            .header("Cookie", cookies)
            .header("X-Requested-With", "com.shopquare.societyhub")
            .build()

        try {
            httpClient.newCall(request).execute().use { response ->
                if (!response.isSuccessful) return

                val bodyStr = response.body?.string().orEmpty()

                // If hit by InfinityFree challenge, solve and sync cookie
                if (bodyStr.contains("slowAES") && bodyStr.contains("toNumbers")) {
                    val testVal = GateAlertManager.solveInfinityFreeChallenge(bodyStr)
                    if (!testVal.isNullOrEmpty()) {
                        try {
                            CookieManager.getInstance().setCookie(BASE_URL, "__test=$testVal")
                            CookieManager.getInstance().flush()
                        } catch (e: Exception) {}
                    }
                    return
                }

                val json = JSONObject(bodyStr)
                if (json.optString("status") == "success") {
                    val dataArray = json.optJSONArray("data")
                    if (dataArray != null && dataArray.length() > 0) {
                        val vis = dataArray.getJSONObject(0)
                        val visitorId = vis.optLong("id", 0L)
                        val name = vis.optString("visitor_name", "Guest")
                        val purpose = vis.optString("purpose", "Entry Request")
                        val unitNumber = vis.optString("unit_number", "Flat")
                        val photoUrl = vis.optString("photo_url", "")
                        val actionToken = vis.optString("action_token", "")

                        if (visitorId > 0L && visitorId != lastAlertedVisitorId) {
                            lastAlertedVisitorId = visitorId
                            handler.post {
                                GateAlertManager.triggerGateAlert(
                                    context = this@GatePollingService,
                                    visitorId = visitorId,
                                    visitorName = name,
                                    purpose = purpose,
                                    unitNumber = unitNumber,
                                    photoUrl = if (photoUrl.isNotEmpty()) photoUrl else null,
                                    actionToken = if (actionToken.isNotEmpty()) actionToken else null
                                )
                            }
                        }
                    } else {
                        if (lastAlertedVisitorId != 0L) {
                            lastAlertedVisitorId = 0L
                            handler.post {
                                GateAlertManager.stopAlarm(this@GatePollingService)
                            }
                        }
                    }
                }
            }
        } catch (e: Exception) {
            Log.d(TAG, "Poll error: ${e.message}")
        }
    }
}
