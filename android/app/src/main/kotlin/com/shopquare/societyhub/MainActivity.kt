package com.shopquare.societyhub

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import android.util.Log
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.core.view.WindowCompat

class MainActivity : ComponentActivity() {

    companion object {
        var instance: MainActivity? = null
            private set
    }

    var webView: WebView? = null
        private set
    private var fileUploadCallback: ValueCallback<Array<Uri>>? = null
    private var cameraPhotoUri: Uri? = null
    private var pendingCameraUri: Uri? = null
    private var pendingWebPermissionRequest: android.webkit.PermissionRequest? = null

    // Direct camera photo launcher (bypasses any gallery/file chooser and scales photo safely)
    private val takePictureLauncher = registerForActivityResult(
        ActivityResultContracts.TakePicture()
    ) { success ->
        if (success && pendingCameraUri != null) {
            try {
                contentResolver.openInputStream(pendingCameraUri!!)?.use { inputStream ->
                    val originalBitmap = android.graphics.BitmapFactory.decodeStream(inputStream)
                    if (originalBitmap != null) {
                        val maxDim = 800
                        val width = originalBitmap.width
                        val height = originalBitmap.height
                        val scale = if (width > maxDim || height > maxDim) {
                            maxDim.toFloat() / Math.max(width, height)
                        } else 1.0f

                        val scaledBitmap = if (scale < 1.0f) {
                            android.graphics.Bitmap.createScaledBitmap(
                                originalBitmap,
                                (width * scale).toInt(),
                                (height * scale).toInt(),
                                true
                            )
                        } else {
                            originalBitmap
                        }

                        val outputStream = java.io.ByteArrayOutputStream()
                        scaledBitmap.compress(android.graphics.Bitmap.CompressFormat.JPEG, 80, outputStream)
                        val bytes = outputStream.toByteArray()
                        val base64 = android.util.Base64.encodeToString(bytes, android.util.Base64.NO_WRAP)
                        val dataUrl = "data:image/jpeg;base64,$base64"
                        runOnUiThread {
                            webView?.evaluateJavascript(
                                "if (typeof handleNativePhotoCaptured === 'function') { handleNativePhotoCaptured('$dataUrl'); }",
                                null
                            )
                        }
                    }
                }
            } catch (e: Exception) {
                Log.e("MainActivity", "Error reading camera capture: ${e.message}")
            }
        }
    }

    // File chooser launcher for guard visitor camera photo upload
    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == RESULT_OK) {
            val data = result.data
            var results: Array<Uri>? = null

            if (data?.data != null) {
                results = arrayOf(data.data!!)
            } else if (cameraPhotoUri != null) {
                results = arrayOf(cameraPhotoUri!!)
            } else {
                results = WebChromeClient.FileChooserParams.parseResult(result.resultCode, data)
            }
            fileUploadCallback?.onReceiveValue(results)
        } else {
            fileUploadCallback?.onReceiveValue(null)
        }
        fileUploadCallback = null
        cameraPhotoUri = null
    }

    // Permission launcher for Camera and Notifications
    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        val notifGranted = permissions[Manifest.permission.POST_NOTIFICATIONS] ?: true
        val cameraGranted = permissions[Manifest.permission.CAMERA] ?: false

        if (!notifGranted) {
            Toast.makeText(this, "Enable notifications to receive urgent gate visitor alerts.", Toast.LENGTH_LONG).show()
        }

        if (cameraGranted && pendingWebPermissionRequest != null) {
            try {
                pendingWebPermissionRequest?.grant(pendingWebPermissionRequest?.resources)
            } catch (e: Exception) {
                Log.e("MainActivity", "Error granting web permission after request: ${e.message}")
            }
            pendingWebPermissionRequest = null
        } else if (pendingWebPermissionRequest != null) {
            try {
                pendingWebPermissionRequest?.deny()
            } catch (_: Exception) {}
            pendingWebPermissionRequest = null
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        instance = this
        WindowCompat.setDecorFitsSystemWindows(window, false)
        window.statusBarColor = android.graphics.Color.parseColor("#0F172A")
        window.navigationBarColor = android.graphics.Color.parseColor("#FFFFFF")

        // 1. Create the persistent loud alert channel
        GateAlertManager.createNotificationChannel(this)

        // 2. Request runtime permissions on Android 13+
        requestAppPermissions()

        // 3. Start persistent background gate monitor service
        GatePollingService.start(this)

        // 5. Handle device Back button in WebView
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView?.canGoBack() == true) {
                    webView?.goBack()
                } else {
                    finish()
                }
            }
        })

        // 6. Render the Full-Screen Hybrid Web Portal
        setContent {
            SocietyPortalView(
                url = "https://society.infy.click",
                onWebViewCreated = { wv ->
                    webView = wv
                    setupWebView(wv)
                }
            )
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        if (instance == this) {
            instance = null
        }
    }

    fun hasOverlayPermission(): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            Settings.canDrawOverlays(this)
        } else {
            true
        }
    }

    fun checkOverlayPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(this)) {
            Toast.makeText(this, "Please enable 'Display over other apps' so gate call alerts can pop up immediately.", Toast.LENGTH_LONG).show()
            try {
                val intent = Intent(
                    Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                    Uri.parse("package:$packageName")
                )
                startActivity(intent)
            } catch (e: Exception) {
                // Some OEM flavors throw ActivityNotFoundException for specific package URIs
                try {
                    val fallback = Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION)
                    startActivity(fallback)
                } catch (_: Exception) {}
            }
        }
    }

    fun requestBatteryOptimizationExemption() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            try {
                val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
                if (!pm.isIgnoringBatteryOptimizations(packageName)) {
                    val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS).apply {
                        data = Uri.parse("package:$packageName")
                    }
                    startActivity(intent)
                }
            } catch (_: Exception) {}
        }
    }

    fun executeApprovalInWeb(visitorId: Long, action: String) {
        runOnUiThread {
            webView?.evaluateJavascript(
                "if (typeof handleVisitorApproval === 'function') { handleVisitorApproval($visitorId, '$action'); }",
                null
            )
        }
    }

    fun launchNativeCameraDirectly() {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            permissionLauncher.launch(arrayOf(Manifest.permission.CAMERA))
            Toast.makeText(this, "Please grant Camera permission to snap visitor photo.", Toast.LENGTH_SHORT).show()
            return
        }

        try {
            val photoFile = java.io.File(cacheDir, "visitor_${System.currentTimeMillis()}.jpg")
            val uri = androidx.core.content.FileProvider.getUriForFile(
                this,
                "com.shopquare.societyhub.fileprovider",
                photoFile
            )
            pendingCameraUri = uri
            takePictureLauncher.launch(uri)
        } catch (e: Exception) {
            Log.e("MainActivity", "launchNativeCameraDirectly error: ${e.message}")
            Toast.makeText(this, "Could not open camera: ${e.message}", Toast.LENGTH_LONG).show()
        }
    }

    private fun requestAppPermissions() {
        val needed = mutableListOf<String>()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                needed.add(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) != PackageManager.PERMISSION_GRANTED) {
            needed.add(Manifest.permission.CAMERA)
        }

        if (needed.isNotEmpty()) {
            permissionLauncher.launch(needed.toTypedArray())
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView(wv: WebView) {
        wv.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            useWideViewPort = true
            loadWithOverviewMode = true
            setSupportZoom(false)
            cacheMode = WebSettings.LOAD_DEFAULT
            mediaPlaybackRequiresUserGesture = false
            mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
        }

        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            setAcceptThirdPartyCookies(wv, true)
            flush()
        }

        wv.addJavascriptInterface(SocietyHubNativeBridge(), "SocietyHubNative")

        wv.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val uri = request?.url ?: return false
                if (uri.scheme == "tel" || uri.scheme == "mailto") {
                    val intent = Intent(Intent.ACTION_VIEW, uri)
                    startActivity(intent)
                    return true
                }
                return false
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                CookieManager.getInstance().flush()
            }
        }

        wv.webChromeClient = object : WebChromeClient() {
            override fun onPermissionRequest(request: android.webkit.PermissionRequest?) {
                runOnUiThread {
                    val req = request ?: return@runOnUiThread
                    val hasCam = ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED
                    if (hasCam) {
                        try {
                            req.grant(req.resources)
                        } catch (e: Exception) {
                            Log.e("MainActivity", "WebChromeClient onPermissionRequest error: ${e.message}")
                        }
                    } else {
                        pendingWebPermissionRequest = req
                        permissionLauncher.launch(arrayOf(Manifest.permission.CAMERA))
                    }
                }
            }

            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                fileUploadCallback?.onReceiveValue(null)
                fileUploadCallback = filePathCallback

                val takePictureIntent = Intent(android.provider.MediaStore.ACTION_IMAGE_CAPTURE)
                var cameraUri: Uri? = null
                try {
                    val photoFile = java.io.File(cacheDir, "visitor_pick_${System.currentTimeMillis()}.jpg")
                    cameraUri = androidx.core.content.FileProvider.getUriForFile(
                        this@MainActivity,
                        "com.shopquare.societyhub.fileprovider",
                        photoFile
                    )
                    cameraPhotoUri = cameraUri
                    takePictureIntent.putExtra(android.provider.MediaStore.EXTRA_OUTPUT, cameraUri)
                    takePictureIntent.addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION or Intent.FLAG_GRANT_READ_URI_PERMISSION)
                } catch (e: Exception) {
                    Log.e("MainActivity", "Camera URI creation error: ${e.message}")
                }

                // If HTML capture attribute is present, launch camera directly!
                if (fileChooserParams?.isCaptureEnabled == true && cameraUri != null) {
                    try {
                        fileChooserLauncher.launch(takePictureIntent)
                        return true
                    } catch (e: Exception) {
                        Log.e("MainActivity", "Direct capture intent launch error: ${e.message}")
                    }
                }

                val contentIntent = Intent(Intent.ACTION_GET_CONTENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = "image/*"
                }

                val chooserIntent = Intent(Intent.ACTION_CHOOSER).apply {
                    putExtra(Intent.EXTRA_INTENT, contentIntent)
                    putExtra(Intent.EXTRA_TITLE, "Visitor Photo")
                    if (cameraUri != null) {
                        putExtra(Intent.EXTRA_INITIAL_INTENTS, arrayOf(takePictureIntent))
                    }
                }

                try {
                    fileChooserLauncher.launch(chooserIntent)
                } catch (e: Exception) {
                    fileUploadCallback = null
                    return false
                }
                return true
            }
        }

        wv.loadUrl("https://society.infy.click")
    }

    override fun onResume() {
        super.onResume()
        CookieManager.getInstance().flush()
    }

    inner class SocietyHubNativeBridge {
        @JavascriptInterface
        fun triggerVisitorAlert(
            visitorId: Long,
            visitorName: String,
            purpose: String,
            unitNumber: String,
            photoUrl: String?
        ) {
            triggerVisitorAlert(visitorId, visitorName, purpose, unitNumber, photoUrl, null)
        }

        @JavascriptInterface
        fun triggerVisitorAlert(
            visitorId: Long,
            visitorName: String,
            purpose: String,
            unitNumber: String,
            photoUrl: String?,
            actionToken: String?
        ) {
            runOnUiThread {
                GateAlertManager.triggerGateAlert(
                    context = this@MainActivity,
                    visitorId = visitorId,
                    visitorName = visitorName,
                    purpose = purpose,
                    unitNumber = unitNumber,
                    photoUrl = photoUrl,
                    actionToken = actionToken
                )
            }
        }

        @JavascriptInterface
        fun stopVisitorAlert() {
            runOnUiThread {
                GateAlertManager.stopAlarm(this@MainActivity)
            }
        }

        @JavascriptInterface
        fun startBackgroundService() {
            runOnUiThread {
                GatePollingService.start(this@MainActivity)
            }
        }

        @JavascriptInterface
        fun stopBackgroundService() {
            runOnUiThread {
                GatePollingService.stop(this@MainActivity)
            }
        }

        @JavascriptInterface
        fun hasOverlayPermission(): Boolean {
            return this@MainActivity.hasOverlayPermission()
        }

        @JavascriptInterface
        fun requestOverlayPermission() {
            runOnUiThread {
                this@MainActivity.checkOverlayPermission()
            }
        }

        @JavascriptInterface
        fun requestBatteryOptimization() {
            runOnUiThread {
                this@MainActivity.requestBatteryOptimizationExemption()
            }
        }

        @JavascriptInterface
        fun shareText(text: String, title: String) {
            runOnUiThread {
                try {
                    val sendIntent = Intent().apply {
                        action = Intent.ACTION_SEND
                        putExtra(Intent.EXTRA_TEXT, text)
                        type = "text/plain"
                    }
                    val shareIntent = Intent.createChooser(sendIntent, title)
                    startActivity(shareIntent)
                } catch (e: Exception) {
                    Log.e("MainActivity", "shareText failed: ${e.message}")
                }
            }
        }

        @JavascriptInterface
        fun openNativeCamera() {
            runOnUiThread {
                this@MainActivity.launchNativeCameraDirectly()
            }
        }

        @JavascriptInterface
        fun isNativeApp(): Boolean = true

        @JavascriptInterface
        fun getAppVersion(): String = "1.0.7"
    }
}

@Composable
fun SocietyPortalView(
    url: String,
    onWebViewCreated: (WebView) -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(androidx.compose.ui.graphics.Color(0xFF0F172A))
            .statusBarsPadding()
            .navigationBarsPadding()
    ) {
        AndroidView(
            modifier = Modifier.fillMaxSize(),
            factory = { context ->
                WebView(context).apply {
                    onWebViewCreated(this)
                }
            }
        )
    }
}
