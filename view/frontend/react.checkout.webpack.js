const path = require("path");

module.exports = {
    resolve: {
        alias: {
            // Override the core component with your custom component
            "@hyva/react-checkout/components/shippingMethod/components/ShippingMethodList":
                path.resolve(__dirname, "web/React/ShippingMethodList.jsx"),
        },
    },
};