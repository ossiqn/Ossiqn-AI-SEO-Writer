jQuery(document).ready(function($) {
    const app = {
        isGenerating: false,
        currentLicense: null,
        charts: {},
        
        init: function() {
            this.bindEvents();
            this.loadLicenseInfo();
            this.setupCharts();
            this.initializeLanguageSelector();
            this.setupAnalytics();
        },

        bindEvents: function() {
            $(document).on('submit', '#ossiqn-global-generator-form', app.handleGeneratorSubmit);
            $(document).on('click', '#analytics-filter-btn', app.loadAnalytics);
            $(document).on('click', '#analytics-export-btn', app.exportAnalyticsCSV);
            $(document).on('submit', '#ossiqn-license-activate', app.activateLicense);
            $(document).on('click', '#api-key-toggle', app.toggleApiKey);
            $(document).on('click', '#api-key-copy', app.copyApiKey);
            $(document).on('submit', '#ossiqn-webhook-form', app.addWebhook);
            $(document).on('click', '.provider-switch', app.switchProvider);
            $(document).on('change', 'select[name="language"]', app.updatePromptPreview);
        },

        handleGeneratorSubmit: function(e) {
            e.preventDefault();

            if (app.isGenerating) return;

            const title = $('input[name="title"]').val().trim();
            const keywords = $('input[name="keywords"]').val().trim();

            if (!title || !keywords) {
                app.showNotification('Title and keywords are required', 'error');
                return;
            }

            app.isGenerating = true;
            const $btn = $('button[type="submit"]', this);
            const originalText = $btn.html();
            $btn.html('⏳ Generating...').prop('disabled', true);

            const formData = {
                action: 'ossiqn_generate_ai_content',
                nonce: ossiqn_global.nonce,
                title: title,
                keywords: keywords,
                language: $('select[name="language"]').val(),
                ai_provider: $('select[name="ai_provider"]').val(),
                style: $('select[name="style"]').val(),
                tone: $('select[name="tone"]').val(),
                word_count: $('select[name="word_count"]').val(),
                category: $('select[name="category"]').val(),
                include_seo: $('input[name="include_seo"]:checked').length > 0,
                include_images: $('input[name="include_images"]:checked').length > 0,
                auto_publish: $('input[name="auto_publish"]:checked').length > 0,
                notify_webhook: $('input[name="notify_webhook"]:checked').length > 0
            };

            $.ajax({
                url: ossiqn_global.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 180000,
                success: function(response) {
                    if (response.success) {
                        app.showNotification(
                            '✅ ' + response.data.message + 
                            '<br><a href="' + response.data.edit_url + '" target="_blank" style="color: #fff;">Edit Post</a>',
                            'success'
                        );
                        $('#ossiqn-global-generator-form')[0].reset();
                        app.loadLicenseInfo();
                        app.updateTokensDisplay(response.data.tokens_remaining);
                    } else {
                        app.showNotification('❌ ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    app.showNotification('❌ Generation failed. Please try again.', 'error');
                },
                complete: function() {
                    $btn.html(originalText).prop('disabled', false);
                    app.isGenerating = false;
                }
            });
        },

        loadLicenseInfo: function() {
            $.ajax({
                url: ossiqn_global.ajax_url,
                type: 'POST',
                data: {
                    action: 'ossiqn_get_analytics',
                    nonce: ossiqn_global.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        const totalTokens = response.data.reduce((sum, item) => sum + item.tokens, 0);
                        const totalCost = response.data.reduce((sum, item) => sum + item.cost, 0);
                        $('.monthly-cost').text(totalCost.toFixed(2));
                    }
                }
            });
        },

        setupCharts: function() {
            if ($('#ossiqn-global-chart').length) {
                app.createContentChart();
            }

            if ($('#analytics-content-chart').length) {
                app.createAnalyticsCharts();
            }
        },

        createContentChart: function() {
            const ctx = document.getElementById('ossiqn-global-chart');
            if (!ctx) return;

            app.charts.content = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: app.generateDateLabels(30),
                    datasets: [{
                        label: 'Daily Generations',
                        data: app.generateMockData(30),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        createAnalyticsCharts: function() {
            const ctxContent = document.getElementById('analytics-content-chart');
            const ctxProvider = document.getElementById('analytics-provider-chart');
            const ctxCost = document.getElementById('analytics-cost-chart');
            const ctxLanguage = document.getElementById('analytics-language-chart');

            if (ctxContent) {
                app.charts.analyticsContent = new Chart(ctxContent, {
                    type: 'bar',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        datasets: [{
                            label: 'Content Generated',
                            data: [5, 10, 8, 15],
                            backgroundColor: '#667eea'
                        }]
                    }
                });
            }

            if (ctxProvider) {
                app.charts.analyticsProvider = new Chart(ctxProvider, {
                    type: 'doughnut',
                    data: {
                        labels: ['Groq', 'OpenAI', 'Cohere'],
                        datasets: [{
                            data: [60, 30, 10],
                            backgroundColor: ['#667eea', '#764ba2', '#e91e63']
                        }]
                    }
                });
            }

            if (ctxCost) {
                app.charts.analyticsCost = new Chart(ctxCost, {
                    type: 'line',
                    data: {
                        labels: app.generateDateLabels(30),
                        datasets: [{
                            label: 'Daily Cost (USD)',
                            data: app.generateMockCostData(30),
                            borderColor: '#f44336',
                            tension: 0.4
                        }]
                    }
                });
            }

            if (ctxLanguage) {
                app.charts.analyticsLanguage = new Chart(ctxLanguage, {
                    type: 'radar',
                    data: {
                        labels: ['English', 'Turkish', 'Spanish', 'French', 'German', 'Arabic'],
                        datasets: [{
                            label: 'Generations by Language',
                            data: [30, 20, 15, 12, 10, 8],
                            backgroundColor: 'rgba(102, 126, 234, 0.2)',
                            borderColor: '#667eea'
                        }]
                    }
                });
            }
        },

        loadAnalytics: function() {
            const from = $('#analytics-from').val();
            const to = $('#analytics-to').val();

            if (!from || !to) {
                app.showNotification('Please select date range', 'error');
                return;
            }

            $.ajax({
                url: ossiqn_global.ajax_url,
                type: 'POST',
                data: {
                    action: 'ossiqn_get_analytics',
                    nonce: ossiqn_global.nonce,
                    from: from,
                    to: to
                },
                success: function(response) {
                    if (response.success) {
                        app.updateAnalyticsDisplay(response.data);
                    }
                }
            });
        },

        updateAnalyticsDisplay: function(data) {
            const dates = data.map(item => item.date);
            const counts = data.map(item => item.count);
            const costs = data.map(item => parseFloat(item.cost || 0));

            if (app.charts.analyticsContent) {
                app.charts.analyticsContent.data.labels = dates;
                app.charts.analyticsContent.data.datasets[0].data = counts;
                app.charts.analyticsContent.update();
            }

            if (app.charts.analyticsCost) {
                app.charts.analyticsCost.data.labels = dates;
                app.charts.analyticsCost.data.datasets[0].data = costs;
                app.charts.analyticsCost.update();
            }
        },

        exportAnalyticsCSV: function() {
            const from = $('#analytics-from').val();
            const to = $('#analytics-to').val();

            if (!from || !to) {
                app.showNotification('Please select date range', 'error');
                return;
            }

            let csv = 'Date,Count,Tokens,Cost,Provider\n';
            
            $.ajax({
                url: ossiqn_global.ajax_url,
                type: 'POST',
                data: {
                    action: 'ossiqn_get_analytics',
                    nonce: ossiqn_global.nonce,
                    from: from,
                    to: to
                },
                success: function(response) {
                    if (response.success) {
                        response.data.forEach(item => {
                            csv += `${item.date},${item.count},${item.tokens},${item.cost},${item.ai_provider}\n`;
                        });

                        const element = document.createElement('a');
                        element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv));
                        element.setAttribute('download', `ossiqn-analytics-${from}-${to}.csv`);
                        element.style.display = 'none';
                        document.body.appendChild(element);
                        element.click();
                        document.body.removeChild(element);

                        app.showNotification('✅ Analytics exported successfully', 'success');
                    }
                }
            });
        },

        activateLicense: function(e) {
            e.preventDefault();

            const licenseKey = $('input[name="license_key"]').val().trim();

            if (!licenseKey) {
                app.showNotification('License key is required', 'error');
                return;
            }

            $.ajax({
                url: ossiqn_global.ajax_url,
                type: 'POST',
                data: {
                    action: 'ossiqn_validate_license',
                    nonce: ossiqn_global.nonce,
                    license_key: licenseKey
                },
                success: function(response) {
                    if (response.success) {
                        app.showNotification('✅ ' + response.data.message, 'success');
                        app.loadLicenseInfo();
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        app.showNotification('❌ ' + response.data.message, 'error');
                    }
                }
            });
        },

        toggleApiKey: function() {
            const $input = $('#api-key-input');
            const type = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', type);
            $(this).text(type === 'password' ? '👁️' : '🙈');
        },

        copyApiKey: function() {
            const $input = $('#api-key-input');
            $input.select();
            document.execCommand('copy');
            app.showNotification('✅ API key copied to clipboard', 'success');
        },

        addWebhook: function(e) {
            e.preventDefault();

            const webhookUrl = $('input[name="webhook_url"]').val();
            const eventType = $('select[name="event_type"]').val();

            if (!webhookUrl || !eventType) {
                app.showNotification('All fields are required', 'error');
                return;
            }

            const webhookElement = $('<div class="webhook-item"><strong>' + eventType + '</strong><br>' + webhookUrl + '<button type="button" class="btn-danger btn-sm">Remove</button></div>');
            $('#webhooks-list').append(webhookElement);

            $('#ossiqn-webhook-form')[0].reset();
            app.showNotification('✅ Webhook added', 'success');
        },

        switchProvider: function() {
            const provider = $(this).data('provider');

            $.ajax({
                url: ossiqn_global.ajax_url,
                type: 'POST',
                data: {
                    action: 'ossiqn_switch_provider',
                    nonce: ossiqn_global.nonce,
                    provider: provider
                },
                success: function(response) {
                    if (response.success) {
                        app.showNotification('✅ Provider switched to ' + provider, 'success');
                        $('.provider-switch').removeClass('active');
                        $('[data-provider="' + provider + '"]').addClass('active');
                    }
                }
            });
        },

        updatePromptPreview: function() {
            const language = $(this).val();
            const languageName = $('option:selected', this).text();
            $('#prompt-preview').text('Content will be generated in ' + languageName);
        },

        initializeLanguageSelector: function() {
            if ($('select[name="language"]').length) {
                $('select[name="language"]').on('change', app.updatePromptPreview);
                app.updatePromptPreview.call($('select[name="language"]')[0]);
            }
        },

        setupAnalytics: function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            $('#analytics-from').val(firstDay.toISOString().split('T')[0]);
            $('#analytics-to').val(today.toISOString().split('T')[0]);
        },

        updateTokensDisplay: function(remaining) {
            $('[data-tokens-remaining]').text(remaining);
        },

        showNotification: function(message, type) {
            const $notification = $('#ossiqn-global-notification');
            $notification
                .removeClass('ossiqn-success ossiqn-error')
                .addClass('ossiqn-' + type)
                .html(message)
                .slideDown(300);

            setTimeout(function() {
                $notification.slideUp(300);
            }, 5000);
        },

        generateDateLabels: function(days) {
            const labels = [];
            const today = new Date();
            for (let i = days - 1; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            return labels;
        },

        generateMockData: function(days) {
            return Array(days).fill(0).map(() => Math.floor(Math.random() * 20));
        },

        generateMockCostData: function(days) {
            return Array(days).fill(0).map(() => (Math.random() * 10).toFixed(2));
        }
    };

    app.init();

    window.ossiqnGlobal = app;
});