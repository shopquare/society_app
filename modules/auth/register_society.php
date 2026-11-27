<?php
// modules/auth/register_society.php
require_once __DIR__ . '/../../config/db.php';
$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Register New Society - SecureGate SaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased">
    <main id="main-content" class="max-w-2xl w-full bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
        <div class="bg-brand-900 px-6 py-6 sm:px-8 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold font-serif tracking-wide">Onboard Your Housing Society</h1>
                <p class="text-xs sm:text-sm text-brand-100 mt-1">Launch an isolated SecureGate workspace with a 14-day free trial.</p>
            </div>
            <a href="<?php echo $base; ?>/login" class="self-start sm:self-auto px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-xs font-semibold text-white transition shrink-0">← Back to Login</a>
        </div>

        <div class="p-6 sm:p-8 space-y-5">
            <form onsubmit="event.preventDefault(); submitSocietySignup();" class="space-y-4 text-sm">
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="rs-name" class="block font-medium text-slate-700 mb-1">Society Name *</label>
                        <input type="text" id="rs-name" required placeholder="Greenfield Heights CHS" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                    <div>
                        <label for="rs-code" class="block font-medium text-slate-700 mb-1">Unique Society Code *</label>
                        <input type="text" id="rs-code" required placeholder="GRN2026" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg uppercase tracking-wider focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="rs-city" class="block font-medium text-slate-700 mb-1">City *</label>
                        <input type="text" id="rs-city" value="Pune" required placeholder="City" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                    <div>
                        <label for="rs-state" class="block font-medium text-slate-700 mb-1">State *</label>
                        <input type="text" id="rs-state" value="Maharashtra" required placeholder="State" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                    <div>
                        <label for="rs-zip" class="block font-medium text-slate-700 mb-1">PIN Code *</label>
                        <input type="text" id="rs-zip" value="411001" required placeholder="PIN Code" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                </div>
                <div>
                    <label for="rs-plan" class="block font-medium text-slate-700 mb-1">Select Subscription Tier *</label>
                    <select id="rs-plan" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900"></select>
                </div>

                <hr class="border-slate-200 my-2">
                <h2 class="text-base font-bold text-slate-900 font-serif">Primary Society Administrator Account</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="rs-fname" class="block font-medium text-slate-700 mb-1">First Name *</label>
                        <input type="text" id="rs-fname" required placeholder="First Name" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                    <div>
                        <label for="rs-lname" class="block font-medium text-slate-700 mb-1">Last Name *</label>
                        <input type="text" id="rs-lname" required placeholder="Last Name" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                    <div>
                        <label for="rs-email" class="block font-medium text-slate-700 mb-1">Official Email *</label>
                        <input type="email" id="rs-email" required placeholder="admin@yoursociety.com" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                    <div>
                        <label for="rs-phone" class="block font-medium text-slate-700 mb-1">Mobile Number *</label>
                        <input type="tel" id="rs-phone" required placeholder="10-Digit Mobile Number" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                    </div>
                </div>
                <div>
                    <label for="rs-pass" class="block font-medium text-slate-700 mb-1">Admin Password * (min 6 chars)</label>
                    <input type="password" id="rs-pass" required minlength="6" placeholder="••••••••" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-900/20 focus:border-brand-900">
                </div>
                <button type="submit" id="rs-btn" class="w-full py-3 bg-brand-900 hover:bg-brand-800 text-white font-bold rounded-xl shadow-sm transition cursor-pointer">Launch Society Workspace →</button>
            </form>
            <div id="rs-msg" role="status" class="hidden p-4 rounded-xl text-sm font-medium border"></div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                const res = await fetch(appPath('/api/register_society.php'));
                const r = await res.json();
                if (res.ok && r.status === 'success') {
                    const plans = r.plans || (r.data && r.data.plans) || [];
                    document.getElementById('rs-plan').innerHTML = plans.map(p => `<option value="${p.id}">${escapeHtml(p.plan_name)} — ₹${parseFloat(p.monthly_price).toFixed(0)}/mo (Up to ${p.max_flats} flats, ${p.max_gates} gates)</option>`).join('');
                }
            } catch (_) {
                showToast('Unable to load subscription tiers.', 'error');
            }
        });

        async function submitSocietySignup() {
            const btn = document.getElementById('rs-btn');
            btn.disabled = true; btn.innerText = 'Creating Workspace...';
            try {
                const res = await fetch(appPath('/api/register_society.php'), {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        csrf_token: document.getElementById('csrf_token').value,
                        society_name: document.getElementById('rs-name').value.trim(),
                        society_code: document.getElementById('rs-code').value.trim(),
                        city: document.getElementById('rs-city').value.trim(),
                        state: document.getElementById('rs-state').value.trim(),
                        zip_code: document.getElementById('rs-zip').value.trim(),
                        plan_id: document.getElementById('rs-plan').value,
                        first_name: document.getElementById('rs-fname').value.trim(),
                        last_name: document.getElementById('rs-lname').value.trim(),
                        email: document.getElementById('rs-email').value.trim(),
                        phone_number: document.getElementById('rs-phone').value.trim(),
                        password: document.getElementById('rs-pass').value
                    })
                });
                const r = await res.json();
                const box = document.getElementById('rs-msg');
                box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'bg-rose-50', 'text-rose-800', 'border-rose-200');
                if (res.ok) {
                    box.classList.add('bg-emerald-50', 'text-emerald-800', 'border-emerald-200');
                    box.innerText = r.message || 'Society onboarded! You can now sign in at /login.';
                } else {
                    box.classList.add('bg-rose-50', 'text-rose-800', 'border-rose-200');
                    box.innerText = r.message || 'Registration failed.';
                }
            } catch (_) {
                showToast('Network error submitting workspace registration.', 'error');
            } finally {
                btn.disabled = false; btn.innerText = 'Launch Society Workspace →';
            }
        }
    </script>
</body>
</html>
