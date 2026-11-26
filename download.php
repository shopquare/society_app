<?php
// /society_app/download.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
$appUrl = htmlspecialchars(appUrl() . appBasePath(), ENT_QUOTES, 'UTF-8');
$apkDirectUrl = $base . '/download/apk';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <meta name="description" content="Download SecureGate Mobile App for Android (direct APK) and iOS (Apple Safari PWA). Instant gate notifications and visitor approvals.">
    <link rel="manifest" href="<?php echo $base; ?>/manifest.json">
    <title>Download SecureGate Mobile App — Android & iOS</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col antialiased text-slate-800">

    <!-- Header Navigation -->
    <header class="bg-brand-900 border-b border-brand-800 sticky top-0 z-40" style="padding-top: env(safe-area-inset-top, 0px);">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden shadow border border-white/20">
                    <img src="<?php echo $base; ?>/assets/icon-192.png" alt="SecureGate Logo" class="w-full h-full object-cover">
                </div>
                <div>
                    <a href="<?php echo $base; ?>/" class="font-serif text-lg sm:text-xl font-bold text-white tracking-wide hover:text-brand-100 transition">SecureGate</a>
                    <span class="block text-[11px] text-brand-200">Mobile Downloads</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?php echo $base; ?>/login" class="px-3.5 py-1.5 text-xs font-bold text-white hover:bg-white/10 rounded-lg transition border border-white/20">
                    Sign In
                </a>
            </div>
        </div>
    </header>

    <!-- Main Hero -->
    <main class="flex-grow max-w-5xl mx-auto w-full px-4 sm:px-6 py-8 sm:py-12">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-brand-50 text-brand-900 border border-brand-200 mb-3">
                <span>📱</span> Official Mobile Apps
            </span>
            <h1 class="font-serif text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">
                Experience SecureGate on Your Phone
            </h1>
            <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                Receive instant loud gate ringtones, full-screen visitor alerts, one-tap entry approvals, and maintenance payments right on your smartphone.
            </p>
        </div>

        <!-- Download Cards Grid (Android & iOS) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">

            <!-- Card 1: Android APK -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xl flex flex-col justify-between relative overflow-hidden group hover:border-emerald-300 transition-all">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-50 rounded-full blur-2xl group-hover:bg-emerald-100 transition-all pointer-events-none"></div>

                <div>
                    <div class="flex items-center justify-between mb-4">
                        <img src="<?php echo $base; ?>/assets/icon-192.png" alt="SecureGate Icon" class="w-14 h-14 rounded-2xl shadow-md border border-slate-200 object-cover">
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-50 text-emerald-800 text-xs font-black rounded-full border border-emerald-200">
                            ✓ v2.0.0 Latest
                        </span>
                    </div>

                    <h2 class="text-2xl font-bold text-slate-900 mb-1 font-serif">Android App</h2>
                    <p class="text-xs text-slate-500 mb-4">Direct APK package with full background gate call alarms.</p>

                    <div class="flex flex-wrap gap-2 mb-6">
                        <span class="text-[11px] bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-md">Size: ~8.8 MB</span>
                        <span class="text-[11px] bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-md">Requires: Android 7.0+</span>
                        <span class="text-[11px] bg-emerald-50 text-emerald-800 font-semibold px-2.5 py-1 rounded-md border border-emerald-200">Full Ringtone Alert</span>
                    </div>

                    <div class="space-y-3 mb-8">
                        <div class="text-xs font-bold text-slate-900 uppercase tracking-wider">How to Install in 3 Steps:</div>
                        <ol class="space-y-2 text-xs text-slate-600">
                            <li class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-black flex items-center justify-center shrink-0 text-[10px]">1</span>
                                <span>Tap the <strong>Download Android APK</strong> button below.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-black flex items-center justify-center shrink-0 text-[10px]">2</span>
                                <span>Open the downloaded file. If your browser asks, allow <em>"Install unknown apps"</em>.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-800 font-black flex items-center justify-center shrink-0 text-[10px]">3</span>
                                <span>Tap <strong>Install</strong> and grant <strong>Notification</strong> permissions so the gate can ring you!</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="space-y-2.5 pt-2">
                    <a href="<?php echo $apkDirectUrl; ?>" download="SecureGate.apk" class="w-full py-3.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Download SecureGate APK (v2.0.0)</span>
                    </a>
                    <button type="button" onclick="shareAppDownload()" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span>🔗</span> Share Link with Residents
                    </button>
                </div>
            </div>

            <!-- Card 2: iOS (Apple iPhone & iPad) -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xl flex flex-col justify-between relative overflow-hidden group hover:border-slate-400 transition-all">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-slate-100 rounded-full blur-2xl group-hover:bg-slate-200 transition-all pointer-events-none"></div>

                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center shadow-md shadow-slate-900/30">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.63-.77 1.06-1.84.94-2.91-.91.04-2.02.61-2.67 1.38-.58.67-1.09 1.76-.95 2.8 1.02.08 2.05-.5 2.68-1.27z"/>
                            </svg>
                        </div>
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-slate-100 text-slate-800 text-xs font-black rounded-full border border-slate-200">
                             Safari Standalone
                        </span>
                    </div>

                    <h2 class="text-2xl font-bold text-slate-900 mb-1 font-serif">Apple iOS (iPhone)</h2>
                    <p class="text-xs text-slate-500 mb-4">Official Progressive Web App with standalone full-screen view and iOS push alerts.</p>

                    <div class="flex flex-wrap gap-2 mb-6">
                        <span class="text-[11px] bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-md">iPhone & iPad</span>
                        <span class="text-[11px] bg-slate-100 text-slate-700 font-semibold px-2.5 py-1 rounded-md">No App Store Wait</span>
                        <span class="text-[11px] bg-blue-50 text-blue-800 font-semibold px-2.5 py-1 rounded-md border border-blue-200">iOS 16.4+ Web Push</span>
                    </div>

                    <div class="space-y-3 mb-8">
                        <div class="text-xs font-bold text-slate-900 uppercase tracking-wider">How to Add to iPhone Home Screen:</div>
                        <ol class="space-y-2 text-xs text-slate-600">
                            <li class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-900 font-black flex items-center justify-center shrink-0 text-[10px]">1</span>
                                <span>Open <strong>Safari</strong> on your iPhone and go to <strong><?php echo $appUrl; ?></strong>.</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-900 font-black flex items-center justify-center shrink-0 text-[10px]">2</span>
                                <span>Tap the <strong>Share</strong> button (the square with an arrow pointing up at the bottom).</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-900 font-black flex items-center justify-center shrink-0 text-[10px]">3</span>
                                <span>Scroll down and tap <strong>"Add to Home Screen"</strong>, then tap <strong>"Add"</strong>.</span>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="space-y-2.5 pt-2">
                    <a href="<?php echo $base; ?>/login" class="w-full py-3.5 px-6 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-lg shadow-slate-900/30 flex items-center justify-center gap-2 transition active:scale-95 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        <span>Open Web App on iPhone</span>
                    </a>
                    <button type="button" onclick="copyAppUrl()" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span id="copy-btn-text">📋 Copy Safari Link</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Frequently Asked Questions / Tips -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-sm max-w-3xl mx-auto">
            <h3 class="font-serif text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                <span>💡</span> Helpful Tips for Residents & Security
            </h3>
            <div class="space-y-4 text-xs sm:text-sm text-slate-600">
                <div>
                    <h4 class="font-bold text-slate-800 mb-1">Why download the Android APK instead of browser?</h4>
                    <p>The Android app runs a dedicated background gate polling service with full-screen incoming call popups (MyGate style). Even if your phone is locked or screen is off, the app rings loud so you never miss a courier or visitor at the gate.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 mb-1">Does iOS support visitor push alerts?</h4>
                    <p>Yes! On iOS 16.4 and newer, once you add SocietyHub to your Home Screen via Safari, you can enable Web Push notifications to receive live visitor entry requests with Accept and Deny buttons.</p>
                </div>
                <div>
                    <h4 class="font-bold text-slate-800 mb-1">Is the APK safe to install?</h4>
                    <p>Yes, the APK is officially compiled, signed, and hosted directly on the SocietyHub server for instant updates.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-6 text-center text-xs mt-auto border-t border-slate-800">
        <div class="max-w-5xl mx-auto px-4">
            <p>© <?php echo date('Y'); ?> SocietyHub. Built for modern gated communities.</p>
            <div class="flex justify-center gap-4 mt-2">
                <a href="<?php echo $base; ?>/login" class="hover:text-white transition">Sign In</a>
                <span class="text-slate-600">•</span>
                <a href="<?php echo $base; ?>/register" class="hover:text-white transition">Register</a>
                <span class="text-slate-600">•</span>
                <a href="<?php echo $apkDirectUrl; ?>" class="hover:text-white transition">Direct APK</a>
            </div>
        </div>
    </footer>

    <script>
        function shareAppDownload() {
            const shareData = {
                title: 'Download SecureGate Mobile App',
                text: 'Get the official SecureGate app for instant visitor gate approvals, loud doorbells, and notices:',
                url: window.location.href
            };
            if (navigator.share) {
                navigator.share(shareData).catch(() => {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(window.location.href);
                showToast('Download link copied to clipboard!', 'success');
            }
        }

        function copyAppUrl() {
            const url = window.location.origin + appBasePath();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    const btn = document.getElementById('copy-btn-text');
                    btn.textContent = '✓ Copied to Clipboard!';
                    setTimeout(() => { btn.textContent = '📋 Copy Safari Link'; }, 2500);
                });
            } else {
                prompt('Copy this URL to open in Safari:', url);
            }
        }
    </script>
</body>
</html>
