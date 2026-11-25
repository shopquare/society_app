package com.shopquare.societyhub

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.os.Bundle
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
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat

class MainActivity : ComponentActivity() {

    private var webView: WebView? = null
    private var fileUploadCallback: ValueCallback<Array<Uri>>? = null

    // File chooser launcher for guard visitor camera photo upload
    private val fileChooserLauncher = registerForActivityResult(
        ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == RESULT_OK) {
            val data = result.data
            val uris = WebChromeClient.FileChooserParams.parseResult(result.resultCode, data)
            fileUploadCallback?.onReceiveValue(uris)
        } else {
            fileUploadCallback?.onReceiveValue(null)
        }
        fileUploadCallback = null
    }

    // Permission launcher for Camera and Notifications
    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        val notifGranted = permissions[Manifest.permission.POST_NOTIFICATIONS] ?: true
        val cameraGranted = permissions[Manifest.permission.CAMERA] ?: true
        if (!notifGranted) {
            Toast.makeText(this, "Enable notifications to receive urgent gate visitor alerts.", Toast.LENGTH_LONG).show()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // 1. Create the persistent loud alert channel
        GateAlertManager.createNotificationChannel(this)

        // 2. Request runtime permissions on Android 13+
        requestAppPermissions()

        // 3. Handle device Back button in WebView
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView?.canGoBack() == true) {
                    webView?.goBack()
                } else {
                    finish()
                }
            }
        })

        // 4. Render the Full-Screen Hybrid Web Portal
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
            domStorageEnabled = true // Persistent localStorage across reboots
            databaseEnabled = true
            useWideViewPort = true
            loadWithOverviewMode = true
            setSupportZoom(false)
            cacheMode = WebSettings.LOAD_DEFAULT
            mediaPlaybackRequiresUserGesture = false
            mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
        }

        // Enable and persist cookies permanently
        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            setAcceptThirdPartyCookies(wv, true)
            flush()
        }

        // Expose Native Bridge into Javascript: window.SocietyHubNative
        wv.addJavascriptInterface(SocietyHubNativeBridge(), "SocietyHubNative")

        wv.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val uri = request?.url ?: return false

                // Keep internal app links inside WebView; open external schemes (tel:, mailto:) via Intent
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
            // Native Camera & File Chooser for gate guards and profile photos
            override fun onShowFileChooser(
                webView: WebView?,
                filePathCallback: ValueCallback<Array<Uri>>?,
                fileChooserParams: FileChooserParams?
            ): Boolean {
                fileUploadCallback?.onReceiveValue(null)
                fileUploadCallback = filePathCallback

                val intent = fileChooserParams?.createIntent() ?: Intent(Intent.ACTION_GET_CONTENT).apply {
                    type = "image/*"
                }

                try {
                    fileChooserLauncher.launch(intent)
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

    /**
     * JavaScript Bridge accessible via window.SocietyHubNative in web pages
     */
    inner class SocietyHubNativeBridge {
        @JavascriptInterface
        fun triggerVisitorAlert(
            visitorId: Long,
            visitorName: String,
            purpose: String,
            unitNumber: String,
            photoUrl: String?
        ) {
            runOnUiThread {
                GateAlertManager.triggerGateAlert(
                    context = this@MainActivity,
                    visitorId = visitorId,
                    visitorName = visitorName,
                    purpose = purpose,
                    unitNumber = unitNumber,
                    photoUrl = photoUrl
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
        fun isNativeApp(): Boolean = true

        @JavascriptInterface
        fun getAppVersion(): String = "1.0.0"
    }
}

@Composable
fun SocietyPortalView(
    url: String,
    onWebViewCreated: (WebView) -> Unit
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
