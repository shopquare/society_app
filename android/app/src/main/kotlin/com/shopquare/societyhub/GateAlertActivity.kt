package com.shopquare.societyhub

import android.os.Build
import android.os.Bundle
import android.view.WindowManager
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import android.webkit.CookieManager
import androidx.compose.ui.platform.LocalContext
import coil.compose.AsyncImage
import coil.request.ImageRequest

class GateAlertActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // Ensure activity wakes up screen and displays over lock screen
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
                WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON or
                WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD or
                WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
            )
        }

        val visitorId = intent.getLongExtra("VISITOR_ID", 0L).let {
            if (it == 0L) intent.getStringExtra("VISITOR_ID")?.toLongOrNull() ?: 0L else it
        }
        val visitorName = intent.getStringExtra("VISITOR_NAME") ?: "Visitor"
        val purpose = intent.getStringExtra("PURPOSE") ?: "General Visit"
        val unitNumber = intent.getStringExtra("UNIT_NUMBER") ?: "Your Unit"
        val photoUrl = intent.getStringExtra("PHOTO_URL")
        val actionToken = intent.getStringExtra("ACTION_TOKEN")

        setContent {
            GateAlertScreen(
                visitorName = visitorName,
                purpose = purpose,
                unitNumber = unitNumber,
                photoUrl = photoUrl,
                onApprove = {
                    handleDecision(visitorId, "Entered", actionToken, "Visitor Approved!")
                },
                onDeny = {
                    handleDecision(visitorId, "Denied", actionToken, "Visitor Denied.")
                }
            )
        }
    }

    private fun handleDecision(visitorId: Long, status: String, actionToken: String?, toastMsg: String) {
        GateAlertManager.stopAlarm(this)
        GateAlertManager.submitApproval(this, visitorId, status, actionToken) { success, msg ->
            val finalMsg = if (success) toastMsg else "Decision recorded: $msg"
            Toast.makeText(this, finalMsg, Toast.LENGTH_SHORT).show()
            finish()
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        GateAlertManager.stopAlarm(this)
    }
}

@Composable
fun GateAlertScreen(
    visitorName: String,
    purpose: String,
    unitNumber: String,
    photoUrl: String?,
    onApprove: () -> Unit,
    onDeny: () -> Unit
) {
    var isSubmitting by remember { mutableStateOf(false) }

    // Pulsing animation for the alert beacon
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 0.95f,
        targetValue = 1.15f,
        animationSpec = infiniteRepeatable(
            animation = tween(800, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseScale"
    )

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = Color(0xFF0F172A)
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            Color(0xFF1E1B4B),
                            Color(0xFF0F172A),
                            Color(0xFF020617)
                        )
                    )
                )
                .padding(24.dp)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(top = 40.dp, bottom = 20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.SpaceBetween
            ) {
                // Top Badge: Alert Beacon
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Box(
                        modifier = Modifier
                            .scale(pulseScale)
                            .background(Color(0xFFEF4444).copy(alpha = 0.2f), CircleShape)
                            .padding(12.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .background(Color(0xFFEF4444), CircleShape)
                                .size(16.dp)
                        )
                    }

                    Spacer(modifier = Modifier.height(16.dp))

                    Text(
                        text = "INCOMING VISITOR AT GATE",
                        color = Color(0xFFFCA5A5),
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 2.sp
                    )
                }

                // Middle: Visitor Info & Photo Card
                val context = LocalContext.current
                val resolvedPhotoUrl = remember(photoUrl) {
                    when {
                        photoUrl.isNullOrEmpty() -> null
                        photoUrl.startsWith("http://") || photoUrl.startsWith("https://") -> {
                            try {
                                val uri = android.net.Uri.parse(photoUrl)
                                val path = uri.path.orEmpty()
                                if (path.contains("get_visitor_photo.php")) {
                                    val cleanPath = if (path.startsWith("/society_app")) path.removePrefix("/society_app") else path
                                    val query = uri.query?.let { "?$it" } ?: ""
                                    "${GateAlertManager.BASE_URL}$cleanPath$query"
                                } else {
                                    photoUrl
                                }
                            } catch (_: Exception) {
                                photoUrl
                            }
                        }
                        else -> {
                            val cleanPath = if (photoUrl.startsWith("/society_app")) photoUrl.removePrefix("/society_app") else photoUrl
                            GateAlertManager.BASE_URL + (if (cleanPath.startsWith("/")) "" else "/") + cleanPath
                        }
                    }
                }

                val imageLoader = remember {
                    coil.ImageLoader.Builder(context)
                        .okHttpClient(GateAlertManager.httpClient)
                        .crossfade(true)
                        .build()
                }

                val imageRequest = remember(resolvedPhotoUrl) {
                    if (resolvedPhotoUrl == null) null else {
                        val cookies = try { CookieManager.getInstance().getCookie(GateAlertManager.BASE_URL).orEmpty() } catch (_: Exception) { "" }
                        val builder = ImageRequest.Builder(context)
                            .data(resolvedPhotoUrl)
                            .crossfade(true)
                            .setHeader("User-Agent", GateAlertManager.getUserAgent(context))
                        if (cookies.isNotEmpty()) {
                            builder.setHeader("Cookie", cookies)
                        }
                        builder.build()
                    }
                }

                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    // Visitor Photo or Placeholder
                    Box(
                        modifier = Modifier
                            .size(150.dp)
                            .clip(CircleShape)
                            .background(Color(0xFF1E293B))
                            .border(4.dp, Color(0xFF4F46E5), CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        var isImageError by remember { mutableStateOf(false) }
                        if (imageRequest != null && !isImageError) {
                            AsyncImage(
                                model = imageRequest,
                                imageLoader = imageLoader,
                                contentDescription = "Visitor Photo",
                                modifier = Modifier.fillMaxSize(),
                                contentScale = ContentScale.Crop,
                                onError = { err ->
                                    android.util.Log.e("GateAlertActivity", "Visitor image load failed: ${err.result.throwable.message}")
                                    isImageError = true
                                }
                            )
                        } else {
                            Text(
                                text = visitorName.take(1).uppercase(),
                                color = Color.White,
                                fontSize = 54.sp,
                                fontWeight = FontWeight.Black
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(8.dp))

                    if (resolvedPhotoUrl.isNullOrEmpty()) {
                        Text(
                            text = "No photo taken by gate guard",
                            color = Color(0xFF94A3B8),
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }

                    Spacer(modifier = Modifier.height(24.dp))

                    Text(
                        text = visitorName,
                        color = Color.White,
                        fontSize = 28.sp,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.Center
                    )

                    Spacer(modifier = Modifier.height(8.dp))

                    Row(
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .background(Color(0xFF4F46E5).copy(alpha = 0.25f), RoundedCornerShape(8.dp))
                                .padding(horizontal = 12.dp, vertical = 6.dp)
                        ) {
                            Text(
                                text = "Unit $unitNumber",
                                color = Color(0xFFC7D2FE),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }

                        Spacer(modifier = Modifier.width(8.dp))

                        Box(
                            modifier = Modifier
                                .background(Color(0xFF334155), RoundedCornerShape(8.dp))
                                .padding(horizontal = 12.dp, vertical = 6.dp)
                        ) {
                            Text(
                                text = purpose,
                                color = Color(0xFF94A3B8),
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }

                // Bottom: Big Call-Style Actions
                if (isSubmitting) {
                    CircularProgressIndicator(
                        color = Color(0xFF4F46E5),
                        modifier = Modifier.size(48.dp)
                    )
                } else {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        // DENY Button
                        Button(
                            onClick = {
                                isSubmitting = true
                                onDeny()
                            },
                            modifier = Modifier
                                .weight(1f)
                                .height(64.dp),
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFFDC2626)
                            )
                        ) {
                            Text(
                                text = "✕ DENY",
                                color = Color.White,
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 1.sp
                            )
                        }

                        // APPROVE Button
                        Button(
                            onClick = {
                                isSubmitting = true
                                onApprove()
                            },
                            modifier = Modifier
                                .weight(1.3f)
                                .height(64.dp),
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFF16A34A)
                            )
                        ) {
                            Text(
                                text = "✓ ALLOW ENTRY",
                                color = Color.White,
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                letterSpacing = 1.sp
                            )
                        }
                    }
                }
            }
        }
    }
}
