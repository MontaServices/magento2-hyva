'use strict';

(function () {
    // Expose Alpine component factory globally for use in x-data
    window.montaCheckout = function () {
        return {
            // State
            DeliveryOptions: [],
            deliveryOptionsIdentifier: 'DeliveryOptions',
            PickupOptions: [],
            pickupOptionsIdentifier: 'PickupOptions',
            SelectedDateOptions: [],
            CustomerLocation: {},
            dayButtonIdentifier: 'selectDayButton',
            shippingOptionIdentifier: 'selectShippingOption',
            mapIdentifier: 'montaPickupsMap',
            pickupsIdentifier: 'pickupPointsList',
            showingMap: false,
            listButtonText: '',
            isSelected: false,
            tabButtons: [
                {id: 'DeliveryOptions', label: 'Delivery', active: false},
                {id: 'PickupOptions', label: 'Pickup', active: false},
            ],
            shipperOptions: [],

            init() {
                // Activate Delivery tab by default
                this.setActiveTab(this.deliveryOptionsIdentifier);

                // Try to hook into Hyvä checkout store/events if available
                const self = this;
                const applyFromMethod = (method) => {
                    if (!method) return;
                    if (method.carrier_code === 'monta') {
                        let raw = method.extension_attributes && (method.extension_attributes.monta_shipping_options || method.extension_attributes['monta_shipping_options']);
                        if (typeof raw === 'string') {
                            raw = JSON.parse(raw);
                        }
                        if (raw && ((raw.DeliveryOptions && raw.DeliveryOptions.length) || (raw.PickupOptions && raw.PickupOptions.length))) {
                            self.setOptions(raw);
                            return;
                        }
                        self.isSelected = true;
                    }
                    self.isSelected = false;
                };

                // 1) Hyvä checkout global store (best effort)
                const hyvaStore = window.Alpine && Alpine.store ? Alpine.store('checkout') : null;
                if (hyvaStore) {
                    // Initial
                    applyFromMethod(hyvaStore.shippingMethod);
                    // Reactive
                    this.$watch(() => Alpine.store('checkout').shippingMethod, (value) => applyFromMethod(value));
                }

                // 2) Listen to common custom events if the store is not available
                document.addEventListener('hyva:shipping-method:changed', (evt) => applyFromMethod(evt.detail && evt.detail.method));
                document.addEventListener('checkout:shipping-method:changed', (evt) => applyFromMethod(evt.detail && evt.detail.method));

                // 3) Fallback polling a few times in case neither is available yet
                let tries = 0;
                const poll = setInterval(() => {
                    tries++;
                    const maybeMethod = (window.hyva && window.hyva.checkout && window.hyva.checkout.shippingMethod)
                        || (window.hyvaCheckout && window.hyvaCheckout.shippingMethod)
                        || null;
                    if (maybeMethod) {
                        applyFromMethod(maybeMethod);
                        clearInterval(poll);
                    }
                    if (tries > 20) clearInterval(poll);
                }, 250);
            },

            setOptions(options) {
                this.DeliveryOptions = options.DeliveryOptions || [];
                this.PickupOptions = options.PickupOptions || [];

                // Default selected day options
                const firstDay = this.DeliveryOptions[0];
                this.SelectedDateOptions = firstDay && firstDay.options ? firstDay.options : [];

                // Setup tabs active based on available data
                // TODO just select the first tab element regardless of what's in there
                const hasDelivery = this.DeliveryOptions.length > 0;
                const hasPickup = this.PickupOptions.length > 0;
                this.setActiveTab(hasDelivery ? this.deliveryOptionsIdentifier : (hasPickup ? this.pickupOptionsIdentifier : this.deliveryOptionsIdentifier));
            },

            setActiveTab(id) {
                this.tabButtons = this.tabButtons.map(tb => ({...tb, active: tb.id === id}));
            },

            clickTab(tabButton) {
                this.setActiveTab(tabButton.id);
            },

            // Actions (ported from legacy delivery.js)
            clickDayTile(option, event) {
                // Focus selection visuals (apply style on clicked element and remove siblings)
                if (event && event.currentTarget) this.focusSelection(event.currentTarget);
                this.SelectedDateOptions = option.options || [];
            },

            clickDeliveryOption(option, event) {
                if (event && event.currentTarget) this.focusSelection(event.currentTarget);

                // Show business hours when pickup selected
                const container = event && event.currentTarget;
                if (container) {
                    // Hide all business hours in group
                    if (container.parentElement && container.parentElement.querySelectorAll) {
                        container.parentElement.querySelectorAll('.business-hours').forEach(function (el) {
                            el.classList.add('hidden');
                        });
                    }
                    // Then show current
                    const hours = container.querySelector && container.querySelector('.business-hours');
                    if (hours) hours.classList.remove('hidden');
                }

                this.updateQuote(option);
                return true;
            },

            clickShipperOption(shipperOption, event) {
                // Manage selected shipper options by unique code
                const checked = !!(event && event.target && event.target.checked);
                if (checked) {
                    if (!this.shipperOptions.find(o => o.code === shipperOption.code)) {
                        this.shipperOptions.push(shipperOption);
                    }
                } else {
                    this.shipperOptions = this.shipperOptions.filter(o => o.code !== shipperOption.code);
                }
                // Update quote with same selected option (if any previously set)
                this.updateQuote();
                return true;
            },

            // Map/list helpers (UI only)
            toggleMap() {
                this.showingMap = !this.showingMap;
            },
            prevPickup() {
            },
            nextPickup() {
            },

            // Utility: focus selection by applying the monta-blue-focus among siblings
            focusSelection(element) {
                if (!element || !element.parentElement) return;
                const siblings = element.parentElement.children;
                Array.from(siblings).forEach(el => el.classList && el.classList.remove('monta-blue-focus'));
                if (element.classList) element.classList.add('monta-blue-focus');
            },

            // Persist selection to the same extension attributes expected by backend
            updateQuote(option = null) {
                try {
                    // Locate a shippingAddress object used by Hyvä checkout if available
                    const hyvaStore = window.Alpine && Alpine.store ? Alpine.store('checkout') : null;
                    const address = (hyvaStore && (hyvaStore.shippingAddress || hyvaStore.quoteAddress)) || {};

                    if (!address.extension_attributes) address.extension_attributes = {};

                    if (!option && address.extension_attributes.shipping_option) {
                        option = JSON.parse(address.extension_attributes.shipping_option);
                    }

                    if (option) {
                        option.shipperOptions = this.shipperOptions;
                        address.extension_attributes.shipping_option = JSON.stringify(option);

                        // Try to assign back to store so Hyvä can include it in subsequent requests
                        if (hyvaStore) {
                            if (hyvaStore.shippingAddress) hyvaStore.shippingAddress = address;
                            if (hyvaStore.quoteAddress) hyvaStore.quoteAddress = address;
                        }

                        // Emit a custom event in case other parts need to react (totals etc.)
                        document.dispatchEvent(new CustomEvent('monta:shippingOptionSelected', {detail: {option: option}}));
                    } else {
                        console.warn('[Monta Hyvä] No shipping option present to update');
                    }
                } catch (e) {
                    console.warn('[Monta Hyvä] Failed to update quote extension attributes', e);
                }
            },
        };
    };
})();