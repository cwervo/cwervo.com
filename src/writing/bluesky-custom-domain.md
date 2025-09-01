---
title: How to Use Your Custom Domain for Your Bluesky Handle
date: "2024-02-22"
---

<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    body {
        font-family: 'Inter', sans-serif;
    }
    .domain-placeholder {
        transition: background-color 0.2s ease-in-out;
    }
</style>

<div class="container mx-auto max-w-3xl p-4 sm:p-6 md:p-8">

    <header class="text-center mb-8">
        <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2">Use Your Domain as a Bluesky Handle</h1>
        <p class="text-blue-400">An interactive guide to setting up your personal domain.</p>
    </header>

    <!-- Domain Input Section -->
    <section class="mb-10">
        <label for="domainInput" class="block text-lg font-medium text-white mb-2">Enter Your Domain</label>
        <input type="text" id="domainInput" placeholder="e.g., cwervo.com" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
        <p class="text-sm text-gray-500 mt-2">Start typing to see the tutorial update in real-time.</p>
    </section>

    <!-- Tutorial Steps -->
    <div class="space-y-8">

        <!-- Step 1: Bluesky Settings -->
        <div class="bg-gray-800/50 p-6 rounded-xl border border-gray-700/50">
            <h2 class="text-2xl font-bold text-white mb-4 flex items-center">
                <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold mr-4">1</span>
                Change Your Handle in Bluesky
            </h2>
            <p class="mb-4">Go to your Bluesky settings, click "Change Handle", and choose the "I have my own domain" option. Enter your domain name there:</p>
            <div class="bg-gray-900 p-3 rounded-lg text-center font-mono text-lg text-blue-300 break-all">
                <span class="domain-placeholder">cwervo.com</span>
            </div>
             <p class="mt-4 text-sm text-gray-400">Bluesky will then provide you with the specific values you need for the next step. For this guide, we'll use the standard values.</p>
        </div>

        <!-- Step 2: DNS Configuration -->
        <div class="bg-gray-800/50 p-6 rounded-xl border border-gray-700/50">
            <h2 class="text-2xl font-bold text-white mb-4 flex items-center">
                <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold mr-4">2</span>
                Configure Your DNS Records
            </h2>
            <p class="mb-6">Log in to your domain provider (e.g., GoDaddy, Namecheap, Cloudflare) and find the DNS settings. You need to create a new <code class="bg-gray-700 text-gray-200 px-1.5 py-0.5 rounded-md">TXT</code> record with the following details:</p>

            <div class="space-y-4">
                <!-- Host/Name Record -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Host / Name</label>
                    <div class="flex items-center bg-gray-900 p-3 rounded-lg font-mono text-green-400">
                        <span class="flex-grow break-all" id="hostValue">_atproto.<span class="domain-placeholder">cwervo.com</span></span>
                        <button class="copy-btn ml-4 px-3 py-1 bg-gray-700 text-white text-xs font-semibold rounded-md hover:bg-gray-600 transition">Copy</button>
                    </div>
                </div>

                <!-- Value/Content Record -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Value / Content</label>
                     <div class="flex items-center bg-gray-900 p-3 rounded-lg font-mono text-green-400">
                        <span class="flex-grow break-all" id="didValue">did=did:plc:***</span>
                        <button class="copy-btn ml-4 px-3 py-1 bg-gray-700 text-white text-xs font-semibold rounded-md hover:bg-gray-600 transition">Copy</button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Note: Replace <code class="text-gray-400">did:plc:***</code> with the actual "did" value provided to you by Bluesky in your settings.</p>
                </div>
            </div>
        </div>

        <!-- Step 3: Verification -->
        <div class="bg-gray-800/50 p-6 rounded-xl border border-gray-700/50">
            <h2 class="text-2xl font-bold text-white mb-4 flex items-center">
                <span class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold mr-4">3</span>
                Verify and Wait
            </h2>
            <p class="mb-4">After saving the DNS record, go back to Bluesky and click the "Verify DNS Record" button. It can sometimes take a few minutes (or longer, in rare cases) for DNS changes to propagate across the internet.</p>
            <p>Once verified, your handle will be <code class="bg-gray-700 text-gray-200 px-1.5 py-0.5 rounded-md font-mono">@<span class="domain-placeholder">cwervo.com</span></code>!</p>
        </div>
    </div>

    <footer class="text-center mt-12 text-gray-600 text-sm">
        <p>This is an unofficial guide. Always follow the specific instructions provided within the Bluesky app.</p>
    </footer>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const domainInput = document.getElementById('domainInput');
        const domainPlaceholders = document.querySelectorAll('.domain-placeholder');
        const hostValueSpan = document.getElementById('hostValue');
        const copyButtons = document.querySelectorAll('.copy-btn');

        const defaultDomain = 'cwervo.com';

        const updateDomainText = (domain) => {
            const displayDomain = domain.trim() === '' ? defaultDomain : domain;

            // Update all simple placeholders
            domainPlaceholders.forEach(span => {
                span.textContent = displayDomain;
            });

            // Specifically update the complex host value
            hostValueSpan.innerHTML = `_atproto.<span class="domain-placeholder">${displayDomain}</span>`;

            // Re-highlight the placeholders briefly on change
            document.querySelectorAll('.domain-placeholder').forEach(el => {
                el.classList.add('bg-blue-500', 'text-white');
                setTimeout(() => {
                    el.classList.remove('bg-blue-500', 'text-white');
                }, 500);
            });
        };

        // Initial setup
        updateDomainText(defaultDomain);

        // Event listener for input changes
        domainInput.addEventListener('input', () => {
            updateDomainText(domainInput.value);
        });

        // Copy functionality
        copyButtons.forEach(button => {
            button.addEventListener('click', () => {
                const textToCopy = button.previousElementSibling.textContent;

                // A fallback for document.execCommand for older browsers or restricted environments
                const unsecuredCopyToClipboard = (text) => {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                    } catch (err) {
                        console.error('Unable to copy to clipboard', err);
                    }
                    document.body.removeChild(textArea);
                };

                unsecuredCopyToClipboard(textToCopy);

                const originalText = button.textContent;
                button.textContent = 'Copied!';
                button.classList.add('bg-green-600');

                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('bg-green-600');
                }, 2000);
            });
        });
    });
</script>
