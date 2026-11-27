<?php
// /society_app/modules/auth/register.php

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/auth_middleware.php';

startSecureSession();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <meta name="description" content="Register as a resident on the SecureGate portal using your society invite code.">
    <title>Society Registration - SecureGate</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 sm:p-6 antialiased text-slate-800">

    <div class="bg-white rounded-2xl shadow-lg w-full max-w-xl overflow-hidden border border-slate-100">

        <div class="bg-brand-900 px-8 py-10 text-center">
            <h1 class="font-serif text-3xl text-white font-semibold mb-2 tracking-wide">Welcome to the Community</h1>
            <p class="text-brand-50 text-sm">Complete your profile to request portal access.</p>
        </div>

        <div class="px-8 sm:px-10 pt-8 pb-2">
            <div class="flex items-center justify-between relative">
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-slate-100 -z-10"></div>
                <div id="progress-bar" class="absolute left-0 top-1/2 -translate-y-1/2 w-0 h-1 bg-brand-900 -z-10 transition-all duration-500"></div>

                <div class="flex flex-col items-center">
                    <div id="indicator-1" class="w-8 h-8 rounded-full bg-brand-900 ring-4 ring-brand-50 text-white flex items-center justify-center text-sm font-semibold shadow-sm transition-all duration-300">1</div>
                    <span class="text-xs font-semibold text-brand-900 mt-2">Account</span>
                </div>
                <div class="flex flex-col items-center">
                    <div id="indicator-2" class="w-8 h-8 rounded-full bg-white border-2 border-slate-200 text-slate-400 flex items-center justify-center text-sm font-semibold transition-all duration-300">2</div>
                    <span id="indicator-text-2" class="text-xs font-medium text-slate-400 mt-2 transition-all duration-300">Profile</span>
                </div>
                <div class="flex flex-col items-center">
                    <div id="indicator-3" class="w-8 h-8 rounded-full bg-white border-2 border-slate-200 text-slate-400 flex items-center justify-center text-sm font-semibold transition-all duration-300">3</div>
                    <span id="indicator-text-3" class="text-xs font-medium text-slate-400 mt-2 transition-all duration-300">Unit</span>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-8 pt-6">
            <form id="registration-form" onsubmit="event.preventDefault();">
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <!-- STEP 1: Account -->
                <div id="step-1" class="space-y-6 block animate-fade-in">
                    <div class="p-5 bg-brand-50/50 rounded-xl border border-brand-900/10">
                        <label for="society_code" class="block text-sm font-semibold text-brand-900 mb-1.5">Society Invite Code *</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" id="society_code" class="w-full uppercase tracking-wider font-medium px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g. SUNSHINE2026">
                            <button type="button" id="btn-verify" onclick="verifySocietyCode()" class="px-5 py-2.5 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors whitespace-nowrap">Verify</button>
                        </div>
                        <p id="society_result" class="text-xs font-medium mt-2.5 hidden"></p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="email" class="block text-sm font-medium text-slate-700">Email Address *</label>
                        <input type="email" id="email" autocomplete="email" required class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="name@example.com">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <div class="space-y-1.5">
                            <label for="password" class="block text-sm font-medium text-slate-700">Password * (min 8 chars)</label>
                            <input type="password" id="password" minlength="8" autocomplete="new-password" required class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="••••••••">
                        </div>
                        <div class="space-y-1.5">
                            <label for="confirm_password" class="block text-sm font-medium text-slate-700">Confirm Password *</label>
                            <input type="password" id="confirm_password" minlength="8" autocomplete="new-password" required class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Profile -->
                <div id="step-2" class="space-y-5 hidden animate-fade-in">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <div class="space-y-1.5">
                            <label for="first_name" class="block text-sm font-medium text-slate-700">First Name *</label>
                            <input type="text" id="first_name" required class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="John">
                        </div>
                        <div class="space-y-1.5">
                            <label for="last_name" class="block text-sm font-medium text-slate-700">Last Name *</label>
                            <input type="text" id="last_name" required class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="Doe">
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label for="phone" class="block text-sm font-medium text-slate-700">Phone Number *</label>
                        <input type="tel" id="phone" required class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="+91 9876543210">
                    </div>
                </div>

                <!-- STEP 3: Unit Mapping -->
                <div id="step-3" class="space-y-6 hidden animate-fade-in">
                    <div class="space-y-2">
                        <span class="block text-sm font-medium text-slate-700">I am residing as a:</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm hover:bg-slate-50 focus:outline-none transition-all has-[:checked]:border-brand-900 has-[:checked]:ring-1 has-[:checked]:ring-brand-900 has-[:checked]:bg-brand-50">
                                <input type="radio" name="occupancy_type" value="owner" class="sr-only" checked onchange="toggleTenantFields(false)">
                                <div class="flex w-full items-center justify-between"><span class="text-sm font-semibold text-slate-900">Owner</span><div class="h-4 w-4 rounded-full border border-slate-300 bg-white flex items-center justify-center indicator"><div class="h-2 w-2 rounded-full bg-brand-900 opacity-0 transition-opacity"></div></div></div>
                            </label>
                            <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm hover:bg-slate-50 focus:outline-none transition-all has-[:checked]:border-brand-900 has-[:checked]:ring-1 has-[:checked]:ring-brand-900 has-[:checked]:bg-brand-50">
                                <input type="radio" name="occupancy_type" value="tenant" class="sr-only" onchange="toggleTenantFields(true)">
                                <div class="flex w-full items-center justify-between"><span class="text-sm font-semibold text-slate-900">Tenant</span><div class="h-4 w-4 rounded-full border border-slate-300 bg-white flex items-center justify-center indicator"><div class="h-2 w-2 rounded-full bg-brand-900 opacity-0 transition-opacity"></div></div></div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <div class="space-y-1.5">
                            <label for="block" class="block text-sm font-medium text-slate-700">Block / Building *</label>
                            <select id="block" class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900">
                                <option value="">Select Block</option>
                                <option value="A">Block A</option>
                                <option value="B">Block B</option>
                                <option value="C">Block C</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="unit_number" class="block text-sm font-medium text-slate-700">Unit Number *</label>
                            <input type="text" id="unit_number" class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:border-brand-900" placeholder="e.g. 405">
                        </div>
                    </div>

                    <div id="tenant_fields" class="hidden grid-cols-1 sm:grid-cols-2 gap-5 pt-2 border-t border-slate-100">
                        <div class="space-y-1.5">
                            <label for="lease_expiry" class="block text-sm font-medium text-slate-700">Lease Expiry Date</label>
                            <input type="date" id="lease_expiry" class="w-full px-4 py-2.5 bg-white rounded-lg border border-slate-300">
                        </div>
                    </div>
                </div>

                <div class="pt-6 flex items-center justify-between border-t border-slate-100 mt-8">
                    <button type="button" id="btn-back" onclick="changeStep(-1)" class="opacity-0 pointer-events-none px-6 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-all">Back</button>
                    <button type="button" id="btn-next" onclick="changeStep(1)" class="px-6 py-2.5 text-sm font-medium text-white bg-brand-900 hover:bg-brand-800 rounded-lg transition-all shadow-sm">
                        <span id="btn-next-text">Continue</span>
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-slate-50 py-5 text-center border-t border-slate-100">
            <p class="text-sm text-slate-600">Already have an account? <a href="<?php echo $base; ?>/login" class="font-semibold text-brand-900 hover:underline">Sign In</a></p>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 3;
        let isSocietyVerified = false;
        let verifiedSocietyId = null;

        async function verifySocietyCode() {
            const codeInput = document.getElementById('society_code');
            const code = codeInput.value.trim().toUpperCase();
            const resultElement = document.getElementById('society_result');
            const verifyBtn = document.getElementById('btn-verify');

            if (!code) {
                showToast('Please enter a society code first.', 'warning');
                return;
            }

            verifyBtn.innerText = 'Checking...';
            verifyBtn.disabled = true;

            try {
                const response = await fetch(appPath('/api/verify_society.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ society_code: code })
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    isSocietyVerified = true;
                    verifiedSocietyId = result.data.society_id;

                    resultElement.innerHTML = `<span class="text-emerald-600 font-semibold">Verified: ${escapeHtml(result.data.society_name)}, ${escapeHtml(result.data.city)}</span>`;
                    resultElement.classList.remove('hidden');

                    verifyBtn.innerText = 'Verified';
                    verifyBtn.className = 'px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-lg whitespace-nowrap';
                    codeInput.readOnly = true;
                    codeInput.classList.add('bg-slate-100', 'text-slate-500');
                } else {
                    isSocietyVerified = false;
                    verifiedSocietyId = null;

                    resultElement.innerHTML = `<span class="text-red-500 font-semibold">${escapeHtml(result.message || 'Invalid code')}</span>`;
                    resultElement.classList.remove('hidden');

                    verifyBtn.innerText = 'Verify';
                    verifyBtn.disabled = false;
                }
            } catch (error) {
                showToast('Failed to connect to the server.', 'error');
                verifyBtn.innerText = 'Verify';
                verifyBtn.disabled = false;
            }
        }

        function changeStep(direction) {
            if (direction === 1) {
                if (currentStep === 1) {
                    if (!isSocietyVerified) { showToast('Please verify your Society Code first.', 'warning'); return; }
                    const email = document.getElementById('email').value.trim();
                    const pass = document.getElementById('password').value;
                    const confirmPass = document.getElementById('confirm_password').value;
                    if (!email || !pass || !confirmPass) { showToast('Please fill out all account fields.', 'warning'); return; }
                    if (pass.length < 8) { showToast('Password must be at least 8 characters.', 'warning'); return; }
                    if (pass !== confirmPass) { showToast('Passwords do not match.', 'error'); return; }
                }
                if (currentStep === 2) {
                    const firstName = document.getElementById('first_name').value.trim();
                    const lastName = document.getElementById('last_name').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    if (!firstName || !lastName || !phone) { showToast('Please fill out your first name, last name, and phone number.', 'warning'); return; }
                }
            }

            if (direction === 1 && currentStep === totalSteps) {
                const block = document.getElementById('block').value.trim();
                const unit = document.getElementById('unit_number').value.trim();
                if (!block || !unit) { showToast('Please select a block and enter your unit number.', 'warning'); return; }
                submitForm();
                return;
            }

            document.getElementById(`step-${currentStep}`).classList.add('hidden');
            document.getElementById(`step-${currentStep}`).classList.remove('block');

            currentStep += direction;

            document.getElementById(`step-${currentStep}`).classList.remove('hidden');
            document.getElementById(`step-${currentStep}`).classList.add('block');

            updateUI();
        }

        function updateUI() {
            const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progress-bar').style.width = `${progressPercent}%`;

            for (let i = 1; i <= totalSteps; i++) {
                const indicator = document.getElementById(`indicator-${i}`);
                const text = document.getElementById(`indicator-text-${i}`);
                if (i <= currentStep) {
                    indicator.className = 'w-8 h-8 rounded-full bg-brand-900 ring-4 ring-brand-50 text-white flex items-center justify-center text-sm font-semibold shadow-sm transition-all duration-300';
                    if (text) text.className = 'text-xs font-semibold text-brand-900 mt-2 transition-all duration-300';
                } else {
                    indicator.className = 'w-8 h-8 rounded-full bg-white border-2 border-slate-200 text-slate-400 flex items-center justify-center text-sm font-semibold transition-all duration-300';
                    if (text) text.className = 'text-xs font-medium text-slate-400 mt-2 transition-all duration-300';
                }
            }

            const backBtn = document.getElementById('btn-back');
            if (currentStep === 1) {
                backBtn.classList.add('opacity-0', 'pointer-events-none');
            } else {
                backBtn.classList.remove('opacity-0', 'pointer-events-none');
            }

            const nextText = document.getElementById('btn-next-text');
            nextText.innerText = currentStep === totalSteps ? 'Submit Registration' : 'Continue';
        }

        function toggleTenantFields(isTenant) {
            const tenantDiv = document.getElementById('tenant_fields');
            if (isTenant) {
                tenantDiv.classList.remove('hidden');
                tenantDiv.classList.add('grid');
            } else {
                tenantDiv.classList.add('hidden');
                tenantDiv.classList.remove('grid');
            }
        }

        async function submitForm() {
            const nextBtn = document.getElementById('btn-next');
            const nextBtnText = document.getElementById('btn-next-text');
            nextBtn.disabled = true;
            nextBtnText.innerText = 'Processing...';

            const formData = {
                societyId: verifiedSocietyId,
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                firstName: document.getElementById('first_name').value.trim(),
                lastName: document.getElementById('last_name').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                occupancyType: document.querySelector('input[name="occupancy_type"]:checked').value,
                block: document.getElementById('block').value,
                unitNumber: document.getElementById('unit_number').value.trim(),
                leaseExpiry: document.getElementById('lease_expiry') ? document.getElementById('lease_expiry').value : null,
                csrf_token: document.getElementById('csrf_token').value
            };

            try {
                const response = await fetch(appPath('/api/register.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const result = await response.json();

                if (response.ok) {
                    showToast(result.message || 'Registration successful!', 'success');
                    setTimeout(() => { window.location.href = appPath('/login'); }, 1200);
                } else {
                    showToast(result.message || 'Registration failed.', 'error');
                    nextBtn.disabled = false;
                    nextBtnText.innerText = 'Submit Registration';
                }
            } catch (e) {
                showToast('Network error occurred while trying to register.', 'error');
                nextBtn.disabled = false;
                nextBtnText.innerText = 'Submit Registration';
            }
        }
    </script>
</body>
</html>
