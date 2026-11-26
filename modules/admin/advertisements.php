<?php
// /society_app/modules/admin/advertisements.php

if (!isset($_SESSION['logged_in']) || !hasRole([1, 2])) {
    header('Location: ' . appBasePath() . '/login');
    exit();
}

$csrfToken = generateCsrfToken();
$base = htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8');
$activeTab = 'advertisements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="app-base" content="<?php echo $base; ?>">
    <title>Company Advertisements - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <script src="<?php echo $base; ?>/assets/js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo $base; ?>/assets/css/style.css">
    <script src="<?php echo $base; ?>/assets/js/app.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans flex flex-col min-h-screen">

    <?php require_once __DIR__ . '/navbar.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">

        <!-- Top Header & Actions -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 font-serif">Company Advertisements</h1>
                    <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-0.5 rounded-full border border-amber-200">Monetization Hub</span>
                </div>
                <p class="text-sm text-slate-500 mt-1">Manage external company campaigns, brand sponsorships, and resident app promotional placements.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" onclick="openPackagesModal()" class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                    <span>📦</span> Ad Packages
                </button>
                <button type="button" onclick="openAnalyticsModal()" class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-2xs transition flex items-center gap-1.5 cursor-pointer">
                    <span>📊</span> Analytics Report
                </button>
                <button type="button" onclick="openCreateAdModal()" class="px-4 py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                    <span>➕</span> New Company Ad
                </button>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-8">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block">Total Ads</span>
                <span id="metric-total-ads" class="text-2xl font-black text-slate-900 block mt-1">--</span>
                <span class="text-[11px] text-slate-500 mt-0.5 block">All campaigns</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-emerald-600 text-xs font-bold uppercase tracking-wider block">Active</span>
                <span id="metric-active-ads" class="text-2xl font-black text-emerald-600 block mt-1">--</span>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Live in resident app</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-blue-600 text-xs font-bold uppercase tracking-wider block">Scheduled</span>
                <span id="metric-scheduled-ads" class="text-2xl font-black text-blue-600 block mt-1">--</span>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Future start date</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-indigo-600 text-xs font-bold uppercase tracking-wider block">Impressions</span>
                <span id="metric-impressions" class="text-2xl font-black text-indigo-600 block mt-1">--</span>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Total resident views</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-amber-600 text-xs font-bold uppercase tracking-wider block">Clicks & CTR</span>
                <div class="flex items-baseline gap-1 mt-1">
                    <span id="metric-clicks" class="text-2xl font-black text-amber-600">--</span>
                    <span id="metric-ctr" class="text-xs font-bold text-slate-500">(--%)</span>
                </div>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Engagement rate</span>
            </div>
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs">
                <span class="text-brand-900 text-xs font-bold uppercase tracking-wider block">Revenue</span>
                <span id="metric-revenue" class="text-2xl font-black text-brand-900 block mt-1">₹0</span>
                <span class="text-[11px] text-slate-500 mt-0.5 block">Verified collections</span>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs mb-6 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex flex-1 items-center gap-2">
                <div class="relative w-full max-w-xs">
                    <input type="text" id="ad-search-input" oninput="debounceFilterAds()" placeholder="Search company, title, or tag..." class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                </div>
                <select id="ad-category-filter" onchange="loadAdsList()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700">
                    <option value="all">All Categories</option>
                    <option value="Food">🍕 Food & Dining</option>
                    <option value="Grocery">🥦 Quick Grocery</option>
                    <option value="Shopping">🛍️ E-commerce & Shopping</option>
                    <option value="Healthcare">🏥 Healthcare & Clinics</option>
                    <option value="Education">🎓 Education & Coaching</option>
                    <option value="Services">🔧 Home Services & Repair</option>
                    <option value="Real_Estate">🏢 Real Estate</option>
                    <option value="Other">📦 Other Brands</option>
                </select>
                <select id="ad-status-filter" onchange="loadAdsList()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700">
                    <option value="all">All Statuses</option>
                    <option value="Active">🟢 Active</option>
                    <option value="Scheduled">📅 Scheduled</option>
                    <option value="Paused">⏸️ Paused</option>
                    <option value="Expired">⏱️ Expired</option>
                    <option value="Draft">📝 Draft</option>
                </select>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>Placements:</span>
                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-1 rounded text-[11px] font-medium">Hero Banner</span>
                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-1 rounded text-[11px] font-medium">Carousel</span>
                <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 px-2 py-1 rounded text-[11px] font-medium">Offers & Deals</span>
            </div>
        </div>

        <!-- Advertisements Table -->
        <div class="bg-white rounded-xl shadow-2xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs whitespace-nowrap">
                    <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-5 py-3.5">Company & Creative</th>
                            <th class="px-4 py-3.5">Category & Offer</th>
                            <th class="px-4 py-3.5">Placements & Target</th>
                            <th class="px-4 py-3.5">Schedule & Status</th>
                            <th class="px-4 py-3.5 text-center">Impressions</th>
                            <th class="px-4 py-3.5 text-center">Clicks / CTR</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ads-table-body" class="divide-y divide-slate-100 text-slate-700">
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-brand-900 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Loading company advertisements...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Create / Edit Advertisement Modal -->
    <div id="ad-form-modal" class="hidden fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden my-6 flex flex-col max-h-[92vh]">
            <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📢</span>
                    <h3 id="ad-modal-title" class="text-base font-bold font-serif">Create Company Advertisement</h3>
                </div>
                <button type="button" onclick="closeAdModal()" class="text-slate-400 hover:text-white p-1 rounded transition cursor-pointer">✕</button>
            </div>

            <form id="ad-campaign-form" onsubmit="handleAdFormSubmit(event)" enctype="multipart/form-data" class="flex-grow overflow-y-auto p-6 space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="form-action" name="action" value="create">
                <input type="hidden" id="form-ad-id" name="ad_id" value="0">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left Form Fields (8 Cols) -->
                    <div class="lg:col-span-7 space-y-4">
                        <!-- Company & Category -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Company / Brand Name <span class="text-rose-500">*</span></label>
                                <input type="text" id="form-company-name" name="company_name" required oninput="updateLivePreview()" placeholder="e.g. Zomato, Swiggy, Blinkit" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Industry / Category <span class="text-rose-500">*</span></label>
                                <select id="form-category" name="category" onchange="updateLivePreview()" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                                    <option value="Food">🍕 Food & Dining</option>
                                    <option value="Grocery">🥦 Quick Grocery</option>
                                    <option value="Shopping">🛍️ E-commerce & Shopping</option>
                                    <option value="Healthcare">🏥 Healthcare & Clinics</option>
                                    <option value="Education">🎓 Education & Coaching</option>
                                    <option value="Services">🔧 Home Services & Repair</option>
                                    <option value="Real_Estate">🏢 Real Estate</option>
                                    <option value="Other">📦 Other Brands</option>
                                </select>
                            </div>
                        </div>

                        <!-- Ad Headline -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Advertisement Headline <span class="text-rose-500">*</span></label>
                            <input type="text" id="form-headline" name="headline" required oninput="updateLivePreview()" placeholder="e.g. Craving Delicious Biryani? Flat 50% OFF!" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Advertisement Details / Body Text</label>
                            <textarea id="form-body-text" name="body_text" rows="2" oninput="updateLivePreview()" placeholder="Offer description, terms, discount codes..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900"></textarea>
                        </div>

                        <!-- URL & Contact Phone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Destination URL <span class="text-rose-500">*</span></label>
                                <input type="url" id="form-target-url" name="target_url" required oninput="updateLivePreview()" placeholder="https://www.company.com/offer" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Contact Phone (Optional)</label>
                                <input type="tel" id="form-contact-phone" name="contact_phone" oninput="updateLivePreview()" placeholder="e.g. +91 9876543210" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                        </div>

                        <!-- Offer Badge & CTA Text -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Offer Badge (Pill Label)</label>
                                <input type="text" id="form-offer-badge" name="offer_badge" oninput="updateLivePreview()" placeholder="e.g. FLAT 50% OFF, 10-MIN DELIVERY" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Call-To-Action Button Text</label>
                                <input type="text" id="form-cta-text" name="cta_text" oninput="updateLivePreview()" placeholder="e.g. Order Now, Shop Now, Call Now" value="View Offer" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                        </div>

                        <!-- Creatives Upload (Logo & Banner) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Company Logo</label>
                                <input type="file" id="form-logo-file" name="company_logo" accept="image/jpeg,image/png,image/webp,image/svg+xml" onchange="previewUploadImage(this, 'preview-logo-img')" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-200 file:text-slate-700 hover:file:bg-slate-300 cursor-pointer">
                                <span class="text-[10px] text-slate-400 mt-1 block">Square SVG/PNG (Max 5MB)</span>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Banner Creative</label>
                                <input type="file" id="form-banner-file" name="banner_image" accept="image/jpeg,image/png,image/webp,image/svg+xml" onchange="previewUploadImage(this, 'preview-banner-img')" class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-slate-200 file:text-slate-700 hover:file:bg-slate-300 cursor-pointer">
                                <span class="text-[10px] text-slate-400 mt-1 block">800x400 Landscape (Max 10MB)</span>
                            </div>
                        </div>

                        <!-- Placements Multi-Select -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Display Placements in Resident App</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <label class="flex items-center gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" name="display_locations[]" value="dashboard_banner" checked class="rounded text-brand-900 focus:ring-brand-900">
                                    <span class="font-medium">Top Banner</span>
                                </label>
                                <label class="flex items-center gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" name="display_locations[]" value="carousel" checked class="rounded text-brand-900 focus:ring-brand-900">
                                    <span class="font-medium">Brand Carousel</span>
                                </label>
                                <label class="flex items-center gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" name="display_locations[]" value="offers_deals" checked class="rounded text-brand-900 focus:ring-brand-900">
                                    <span class="font-medium">Offers & Deals Hub</span>
                                </label>
                                <label class="flex items-center gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" name="display_locations[]" value="dashboard_cards" checked class="rounded text-brand-900 focus:ring-brand-900">
                                    <span class="font-medium">Dashboard Cards</span>
                                </label>
                                <label class="flex items-center gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" name="display_locations[]" value="marketplace" class="rounded text-brand-900 focus:ring-brand-900">
                                    <span class="font-medium">Marketplace Sponsor</span>
                                </label>
                                <label class="flex items-center gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs cursor-pointer hover:bg-slate-100">
                                    <input type="checkbox" name="display_locations[]" value="notice_board" class="rounded text-brand-900 focus:ring-brand-900">
                                    <span class="font-medium">Notice Board</span>
                                </label>
                            </div>
                        </div>

                        <!-- Schedule, Audience, Priority & Status -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Start Date</label>
                                <input type="date" id="form-start-date" name="start_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">End Date</label>
                                <input type="date" id="form-end-date" name="end_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Priority (1-10)</label>
                                <input type="number" id="form-priority" name="priority" min="1" max="10" value="5" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                                <select id="form-status" name="status" class="w-full px-2.5 py-1.5 bg-white border border-slate-300 rounded-lg text-xs focus:ring-2 focus:border-brand-900">
                                    <option value="Active">Active</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Paused">Paused</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Live Resident Preview (5 Cols) -->
                    <div class="lg:col-span-5 bg-slate-100 p-4 rounded-xl border border-slate-200 flex flex-col">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <span>📱</span> Live Resident App Preview
                        </span>

                        <div class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden mt-auto mb-auto flex flex-col">
                            <!-- Banner Image Area -->
                            <div class="relative w-full h-36 bg-slate-900 flex items-center justify-center overflow-hidden">
                                <img id="preview-banner-img" src="<?php echo $base; ?>/uploads/ads/banners/zomato_banner.svg" alt="Banner Preview" class="w-full h-full object-cover">
                                <div id="preview-badge-pill" class="absolute top-2.5 left-2.5 bg-amber-400 text-amber-950 text-[10px] font-black px-2 py-0.5 rounded-full shadow uppercase tracking-wide">
                                    FLAT 50% OFF
                                </div>
                                <span class="absolute top-2.5 right-2.5 bg-black/60 backdrop-blur-xs text-white text-[9px] font-semibold px-1.5 py-0.5 rounded">
                                    Sponsored
                                </span>
                            </div>

                            <!-- Card Body -->
                            <div class="p-3.5 space-y-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                                        <img id="preview-logo-img" src="<?php echo $base; ?>/uploads/ads/logos/zomato.svg" alt="Logo Preview" class="w-full h-full object-contain">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span id="preview-company-name" class="font-bold text-xs text-slate-900 block truncate">Zomato</span>
                                        <span id="preview-category-text" class="text-[10px] text-slate-500 block">🍕 Food & Dining</span>
                                    </div>
                                </div>

                                <h4 id="preview-headline" class="font-bold text-xs text-slate-900 leading-snug line-clamp-2">
                                    Craving Delicious Food? Flat 50% OFF!
                                </h4>
                                <p id="preview-body" class="text-[11px] text-slate-600 line-clamp-2">
                                    Order your favorite biryani, pizza, North Indian delicacies & desserts from top-rated restaurants near you.
                                </p>

                                <div class="pt-1.5 flex items-center justify-between gap-2 border-t border-slate-100">
                                    <span id="preview-contact-wrap" class="hidden text-[10px] text-slate-500 flex items-center gap-1">
                                        📞 <span id="preview-phone-text">9876543210</span>
                                    </span>
                                    <button type="button" id="preview-cta-btn" class="ml-auto px-3.5 py-1.5 bg-brand-900 hover:bg-brand-800 text-white font-bold text-[11px] rounded-lg shadow-xs transition">
                                        Order Now →
                                    </button>
                                </div>
                            </div>
                        </div>

                        <span class="text-[10px] text-slate-400 text-center mt-3 block">
                            Advertisements are strictly excluded from SOS Emergency, Guard Gate, and Auth screens.
                        </span>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeAdModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-lg transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" id="btn-save-ad" class="px-5 py-2 bg-brand-900 hover:bg-brand-800 text-white font-bold text-xs rounded-lg shadow-sm transition cursor-pointer flex items-center gap-1.5">
                        <span>💾</span> Save Advertisement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ad Packages Modal -->
    <div id="packages-modal" class="hidden fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📦</span>
                    <h3 class="text-base font-bold font-serif">Advertising Packages & Pricing</h3>
                </div>
                <button type="button" onclick="closePackagesModal()" class="text-slate-400 hover:text-white p-1 rounded transition cursor-pointer">✕</button>
            </div>
            <div class="p-6">
                <p class="text-xs text-slate-500 mb-4">Set society advertising packages for external brands and resident commercial promotions.</p>
                <div id="packages-list-container" class="space-y-3 max-h-80 overflow-y-auto">
                    <!-- Loaded dynamically -->
                </div>
            </div>
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closePackagesModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-lg transition cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    <!-- Analytics Report Modal -->
    <div id="analytics-modal" class="hidden fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[85vh]">
            <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xl">📊</span>
                    <h3 class="text-base font-bold font-serif">Advertisement Engagement & Analytics</h3>
                </div>
                <button type="button" onclick="closeAnalyticsModal()" class="text-slate-400 hover:text-white p-1 rounded transition cursor-pointer">✕</button>
            </div>
            <div class="p-6 flex-grow overflow-y-auto">
                <div id="analytics-breakdown-container">
                    <!-- Loaded dynamically -->
                </div>
            </div>
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeAnalyticsModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-lg transition cursor-pointer">Close</button>
            </div>
        </div>
    </div>

    <script>
        let companyAds = [];
        let adPackages = [];
        let debounceTimer = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadAdsList();
        });

        function debounceFilterAds() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                loadAdsList();
            }, 300);
        }

        async function loadAdsList() {
            const tbody = document.getElementById('ads-table-body');
            const search = document.getElementById('ad-search-input')?.value || '';
            const category = document.getElementById('ad-category-filter')?.value || 'all';
            const status = document.getElementById('ad-status-filter')?.value || 'all';

            try {
                const params = new URLSearchParams({ search, category, status });
                const res = await fetch(appPath(`/api/admin_manage_company_ads.php?${params.toString()}`));
                const data = await res.json();

                if (data.status === 'success') {
                    companyAds = data.data.ads || [];
                    adPackages = data.data.packages || [];
                    updateMetrics(data.data.metrics || {});
                    renderAdsTable(companyAds);
                } else {
                    tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-rose-500 font-bold">${escapeHtml(data.message || 'Failed to load advertisements')}</td></tr>`;
                }
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-8 text-center text-rose-500 font-bold">Network error while fetching advertisements.</td></tr>`;
            }
        }

        function updateMetrics(m) {
            document.getElementById('metric-total-ads').textContent = m.total_ads ?? 0;
            document.getElementById('metric-active-ads').textContent = m.active_campaigns ?? 0;
            document.getElementById('metric-scheduled-ads').textContent = m.scheduled_campaigns ?? 0;
            document.getElementById('metric-impressions').textContent = Number(m.total_impressions || 0).toLocaleString();
            document.getElementById('metric-clicks').textContent = Number(m.total_clicks || 0).toLocaleString();
            document.getElementById('metric-ctr').textContent = `(${m.avg_ctr || 0}%)`;
            document.getElementById('metric-revenue').textContent = `₹${Number(m.revenue || 0).toLocaleString()}`;
        }

        function renderAdsTable(ads) {
            const tbody = document.getElementById('ads-table-body');
            if (!ads || ads.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-500">
                            <span class="text-3xl block mb-2">📢</span>
                            <span class="font-bold text-slate-700 block">No advertisements found</span>
                            <span class="text-xs text-slate-400 mt-1 block">Click "+ New Company Ad" to launch your first brand campaign.</span>
                        </td>
                    </tr>`;
                return;
            }

            tbody.innerHTML = ads.map(ad => {
                const logoSrc = ad.logo_url || `${appPath('/assets/img/app-logo.svg')}`;
                const bannerSrc = ad.banner_url || '';
                const calculatedStatus = ad.calculated_status || ad.status;

                let statusBadge = '';
                if (calculatedStatus === 'Active') {
                    statusBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">🟢 Active</span>';
                } else if (calculatedStatus === 'Scheduled') {
                    statusBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">📅 Scheduled</span>';
                } else if (calculatedStatus === 'Paused') {
                    statusBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">⏸️ Paused</span>';
                } else if (calculatedStatus === 'Expired') {
                    statusBadge = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">⏱️ Expired</span>';
                } else {
                    statusBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">${escapeHtml(calculatedStatus)}</span>`;
                }

                const locations = (ad.display_locations || '').split(',').map(l => l.trim()).filter(Boolean);
                const locBadges = locations.slice(0, 2).map(l => `<span class="bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded text-[9px] font-medium border border-slate-200">${escapeHtml(l)}</span>`).join(' ') + (locations.length > 2 ? ` <span class="text-[9px] text-slate-400">+${locations.length - 2}</span>` : '');

                return `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center p-1 shrink-0 overflow-hidden">
                                    <img src="${escapeHtml(logoSrc)}" alt="${escapeHtml(ad.company_name || '')}" class="w-full h-full object-contain">
                                </div>
                                <div class="min-w-0 max-w-[200px]">
                                    <span class="font-bold text-slate-900 block truncate">${escapeHtml(ad.company_name || ad.advertiser_name)}</span>
                                    <span class="text-slate-500 text-[11px] block truncate font-medium">${escapeHtml(ad.headline)}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-bold border border-slate-200 mb-1">
                                ${escapeHtml(ad.category || 'Other')}
                            </span>
                            ${ad.offer_badge ? `<span class="block text-[10px] font-black text-amber-700 truncate">🏷️ ${escapeHtml(ad.offer_badge)}</span>` : ''}
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="space-y-1">
                                <div>${locBadges}</div>
                                <span class="text-[10px] text-slate-400 block capitalize">Audience: ${escapeHtml(ad.target_audience || 'All')}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="space-y-1">
                                ${statusBadge}
                                <span class="text-[10px] text-slate-500 font-mono block">${escapeHtml(ad.start_date)} to ${escapeHtml(ad.end_date)}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center font-bold text-slate-700">
                            ${Number(ad.impressions_count || 0).toLocaleString()}
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="font-bold text-slate-800">${Number(ad.clicks_count || 0).toLocaleString()}</span>
                            <span class="text-[10px] text-slate-500 block font-medium">(${ad.ctr || 0}%)</span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="${escapeHtml(ad.target_url || '#')}" target="_blank" rel="noopener noreferrer" class="p-1.5 text-slate-500 hover:text-brand-900 hover:bg-slate-100 rounded-lg transition" title="Open Destination URL">
                                    🔗
                                </a>
                                <button type="button" onclick="toggleAdStatus(${ad.id}, '${calculatedStatus === 'Active' ? 'Paused' : 'Active'}')" class="p-1.5 text-slate-500 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition cursor-pointer" title="${calculatedStatus === 'Active' ? 'Pause Campaign' : 'Resume Campaign'}">
                                    ${calculatedStatus === 'Active' ? '⏸️' : '▶️'}
                                </button>
                                <button type="button" onclick="editAd(${ad.id})" class="p-1.5 text-slate-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition cursor-pointer" title="Edit Campaign">
                                    ✏️
                                </button>
                                <button type="button" onclick="deleteAd(${ad.id}, '${escapeHtml(ad.company_name || ad.advertiser_name)}')" class="p-1.5 text-slate-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition cursor-pointer" title="Delete Campaign">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function openCreateAdModal() {
            document.getElementById('ad-modal-title').textContent = 'Create Company Advertisement';
            document.getElementById('form-action').value = 'create';
            document.getElementById('form-ad-id').value = '0';
            document.getElementById('ad-campaign-form').reset();
            document.getElementById('form-cta-text').value = 'View Offer';
            document.getElementById('form-start-date').value = new Date().toISOString().split('T')[0];
            const future = new Date();
            future.setDate(future.getDate() + 30);
            document.getElementById('form-end-date').value = future.toISOString().split('T')[0];
            updateLivePreview();
            document.getElementById('ad-form-modal').classList.remove('hidden');
        }

        function editAd(adId) {
            const ad = companyAds.find(a => Number(a.id) === Number(adId));
            if (!ad) return;

            document.getElementById('ad-modal-title').textContent = `Edit Advertisement #${ad.id}`;
            document.getElementById('form-action').value = 'update';
            document.getElementById('form-ad-id').value = ad.id;

            document.getElementById('form-company-name').value = ad.company_name || ad.advertiser_name || '';
            document.getElementById('form-category').value = ad.category || 'Food';
            document.getElementById('form-headline').value = ad.headline || '';
            document.getElementById('form-body-text').value = ad.body_text || '';
            document.getElementById('form-target-url').value = ad.target_url || '';
            document.getElementById('form-contact-phone').value = ad.contact_phone || '';
            document.getElementById('form-offer-badge').value = ad.offer_badge || '';
            document.getElementById('form-cta-text').value = ad.cta_text || 'View Offer';
            document.getElementById('form-start-date').value = ad.start_date || '';
            document.getElementById('form-end-date').value = ad.end_date || '';
            document.getElementById('form-priority').value = ad.priority || 5;
            document.getElementById('form-status').value = ad.status || 'Active';

            // Checkboxes
            const locs = (ad.display_locations || '').split(',').map(l => l.trim());
            document.querySelectorAll('input[name="display_locations[]"]').forEach(cb => {
                cb.checked = locs.includes(cb.value);
            });

            if (ad.logo_url) {
                document.getElementById('preview-logo-img').src = ad.logo_url;
            }
            if (ad.banner_url) {
                document.getElementById('preview-banner-img').src = ad.banner_url;
            }

            updateLivePreview();
            document.getElementById('ad-form-modal').classList.remove('hidden');
        }

        function closeAdModal() {
            document.getElementById('ad-form-modal').classList.add('hidden');
        }

        function updateLivePreview() {
            const name = document.getElementById('form-company-name')?.value || 'Brand Name';
            const headline = document.getElementById('form-headline')?.value || 'Promotional Headline';
            const body = document.getElementById('form-body-text')?.value || 'Exclusive deals and discounts for society residents.';
            const badge = document.getElementById('form-offer-badge')?.value || '';
            const cta = document.getElementById('form-cta-text')?.value || 'View Offer';
            const cat = document.getElementById('form-category')?.value || 'Food';
            const phone = document.getElementById('form-contact-phone')?.value || '';

            document.getElementById('preview-company-name').textContent = name;
            document.getElementById('preview-headline').textContent = headline;
            document.getElementById('preview-body').textContent = body;
            document.getElementById('preview-cta-btn').textContent = `${cta} →`;
            document.getElementById('preview-category-text').textContent = cat;

            const badgePill = document.getElementById('preview-badge-pill');
            if (badge) {
                badgePill.textContent = badge;
                badgePill.classList.remove('hidden');
            } else {
                badgePill.classList.add('hidden');
            }

            const contactWrap = document.getElementById('preview-contact-wrap');
            if (phone) {
                document.getElementById('preview-phone-text').textContent = phone;
                contactWrap.classList.remove('hidden');
            } else {
                contactWrap.classList.add('hidden');
            }
        }

        function previewUploadImage(input, previewImgId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById(previewImgId).src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        async function handleAdFormSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-save-ad');
            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span> Saving...';

            const form = document.getElementById('ad-campaign-form');
            const formData = new FormData(form);

            try {
                const res = await fetch(appPath('/api/admin_manage_company_ads.php'), {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.status === 'success') {
                    showToast(data.message || 'Saved successfully!', 'success');
                    closeAdModal();
                    loadAdsList();
                } else {
                    showToast(data.message || 'Failed to save advertisement', 'error');
                }
            } catch (err) {
                showToast('Network error while saving advertisement.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>💾</span> Save Advertisement';
            }
        }

        async function toggleAdStatus(adId, newStatus) {
            try {
                const res = await fetch(appPath('/api/admin_manage_company_ads.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'toggle_status',
                        ad_id: adId,
                        new_status: newStatus,
                        csrf_token: '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>'
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    showToast(data.message || 'Status updated', 'success');
                    loadAdsList();
                } else {
                    showToast(data.message || 'Unable to update status', 'error');
                }
            } catch (e) {
                showToast('Network error occurred.', 'error');
            }
        }

        async function deleteAd(adId, companyName) {
            if (!confirm(`Are you sure you want to permanently delete the advertisement campaign for "${companyName}"?`)) {
                return;
            }

            try {
                const res = await fetch(appPath('/api/admin_manage_company_ads.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        ad_id: adId,
                        csrf_token: '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>'
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    showToast(data.message || 'Advertisement deleted', 'success');
                    loadAdsList();
                } else {
                    showToast(data.message || 'Unable to delete advertisement', 'error');
                }
            } catch (e) {
                showToast('Network error occurred.', 'error');
            }
        }

        function openPackagesModal() {
            const container = document.getElementById('packages-list-container');
            if (adPackages.length === 0) {
                container.innerHTML = '<div class="text-center text-slate-400 py-4 text-xs">No advertising packages defined.</div>';
            } else {
                container.innerHTML = adPackages.map(pkg => `
                    <div class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                        <div>
                            <span class="font-bold text-slate-900 text-xs block">${escapeHtml(pkg.package_name)}</span>
                            <span class="text-slate-500 text-[11px] block mt-0.5">${escapeHtml(pkg.description || '')} • <strong>${pkg.duration_days} Days</strong></span>
                        </div>
                        <div class="text-right">
                            <span class="text-brand-900 font-black text-sm block">₹${Number(pkg.price).toLocaleString()}</span>
                            <span class="text-[10px] text-emerald-600 font-bold">Active</span>
                        </div>
                    </div>
                `).join('');
            }
            document.getElementById('packages-modal').classList.remove('hidden');
        }

        function closePackagesModal() {
            document.getElementById('packages-modal').classList.add('hidden');
        }

        function openAnalyticsModal() {
            const container = document.getElementById('analytics-breakdown-container');
            if (companyAds.length === 0) {
                container.innerHTML = '<div class="text-center text-slate-400 py-6 text-xs">No analytics data available yet.</div>';
            } else {
                container.innerHTML = `
                    <div class="space-y-4">
                        <div class="text-xs text-slate-500 font-medium">Campaign-wise Resident Engagement Breakdown:</div>
                        <div class="space-y-3">
                            ${companyAds.map(ad => {
                                const imp = Number(ad.impressions_count || 0);
                                const clk = Number(ad.clicks_count || 0);
                                const ctr = ad.ctr || 0;
                                const barWidth = Math.min(100, Math.max(5, ctr * 5));

                                return `
                                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-1.5">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-slate-900">${escapeHtml(ad.company_name || ad.advertiser_name)}</span>
                                            <span class="font-mono text-slate-600"><strong>${clk}</strong> clicks / <strong>${imp}</strong> views (${ctr}% CTR)</span>
                                        </div>
                                        <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden">
                                            <div class="bg-brand-900 h-full rounded-full" style="width: ${barWidth}%"></div>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
            }
            document.getElementById('analytics-modal').classList.remove('hidden');
        }

        function closeAnalyticsModal() {
            document.getElementById('analytics-modal').classList.add('hidden');
        }
    </script>
</body>
</html>
