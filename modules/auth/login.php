<?php
// /society_app/modules/auth/login.php

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . getRoleDashboardPath($_SESSION['role_id'] ?? 0));
    exit();
}

$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="app-base" content="<?php echo $base; ?>">
    <meta name="description" content="Sign in to the SocietyHub portal for residents, committee admins, and security staff.">
    <link rel="manifest" href="<?php echo $base; ?>/manifest.json">
    <title>Society Login - Portal Access</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6 antialiased text-slate-800">

    <div class="bg-white rounded-2xl shadow-lg w-full max-w-md overflow-hidden border border-slate-100">

        <div class="bg-brand-900 px-8 py-10 text-center">
            <div class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            </div>
            <h1 class="font-serif text-3xl text-white font-semibold mb-2 tracking-wide">Welcome Back</h1>
            <p class="text-brand-50 text-sm">Sign in to manage your society portal.</p>
        </div>

        <div class="p-8 pt-8">
            <div id="error-alert" role="alert" class="hidden mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-md flex items-start gap-3 text-sm">
                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div class="text-red-800 font-medium" id="error-text">Invalid email or password.</div>
            </div>

            <form id="login-form" onsubmit="event.preventDefault(); handleLogin();" class="space-y-6">
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-medium text-slate-700">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </span>
                        <input type="email" id="email" autocomplete="email" required class="w-full pl-11 pr-4 py-3 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900 transition-colors" placeholder="name@example.com">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </span>
                        <input type="password" id="password" autocomplete="current-password" required class="w-full pl-11 pr-12 py-3 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900 transition-colors" placeholder="••••••••">

                        <button type="button" onclick="togglePassword()" aria-label="Toggle password visibility" class="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 text-slate-400 hover:text-slate-600 focus:outline-none transition-colors">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label for="remember" class="flex items-center gap-2 cursor-pointer group py-1">
                        <div class="relative flex items-center justify-center">
                            <input type="checkbox" id="remember" class="peer appearance-none w-4 h-4 border border-slate-300 rounded bg-white checked:bg-brand-900 checked:border-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-900/20 transition-all cursor-pointer">
                            <svg class="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <span class="text-slate-600 group-hover:text-slate-900 select-none transition-colors">Remember me</span>
                    </label>

                    <button type="button" onclick="showToast('Please contact your society committee administrator to reset your password.', 'info')" class="font-medium text-brand-900 hover:text-brand-800 transition-colors">Forgot Password?</button>
                </div>

                <button type="submit" id="btn-login" class="w-full py-3 px-4 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg shadow-sm hover:shadow transition-all active:scale-95 flex items-center justify-center gap-2">
                    <span id="btn-text">Sign In</span>
                    <svg id="btn-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                </button>
            </form>
        </div>

        <div class="bg-slate-50 py-5 text-center border-t border-slate-100">
            <p class="text-sm text-slate-600">
                Don't have an account?
                <a href="<?php echo $base; ?>/register" class="font-semibold text-brand-900 hover:text-brand-800 hover:underline transition-all">Request access</a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>`;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>`;
            }
        }

        async function handleLogin() {
            const btn = document.getElementById('btn-login');
            const btnText = document.getElementById('btn-text');
            const btnIcon = document.getElementById('btn-icon');
            const emailInput = document.getElementById('email').value;
            const passwordInput = document.getElementById('password').value;
            const rememberInput = document.getElementById('remember').checked;
            const csrfInput = document.getElementById('csrf_token').value;
            const errorAlert = document.getElementById('error-alert');
            const errorText = document.getElementById('error-text');

            errorAlert.classList.add('hidden');
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btnText.innerText = 'Verifying...';
            btnIcon.classList.add('hidden');

            try {
                const response = await fetch(appPath('/api/login.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: emailInput,
                        password: passwordInput,
                        remember: rememberInput,
                        csrf_token: csrfInput
                    })
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    btnText.innerText = 'Success! Redirecting...';
                    window.location.href = (result.data && result.data.redirect) ? result.data.redirect : appPath('/login');
                } else {
                    errorText.textContent = result.message || 'Invalid credentials.';
                    errorAlert.classList.remove('hidden');
                    btn.disabled = false;
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                    btnText.innerText = 'Sign In';
                    btnIcon.classList.remove('hidden');
                }
            } catch (error) {
                errorText.textContent = 'Failed to connect to the server. Please try again.';
                errorAlert.classList.remove('hidden');
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                btnText.innerText = 'Sign In';
                btnIcon.classList.remove('hidden');
            }
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register(appPath('/sw.js'), { scope: appPath('/') || '/' })
                    .catch(function (err) {
                        console.error('ServiceWorker registration failed:', err);
                    });
            });
        }
    </script>
</body>
</html>
