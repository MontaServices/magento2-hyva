import {useCheckoutFormContext} from '@hyva/checkout';

export default function Options({method}) {
    const {registerField, setFieldValue, values} = useCheckoutFormContext();

    const options = method?.extension_attributes?.monta_shipping_options || [];
    console.log("Hyva component for " + method + "Found options: ", options);

    if (!options.length) {
        return null; // no extra options for this carrier
    }

    const fieldName = `shipping_address.extension_attributes.shipping_option`;

    return (
        <div className="mt-2 pl-4 border-l">
            <h4 className="font-medium mb-2">Select Shipping Option</h4>

            {options.map((option, index) => (
                <button
                    key={index}
                    type="button"
                    className={`block w-full text-left p-2 rounded border mb-1 ${
                        values[fieldName] === JSON.stringify(option)
                            ? 'border-blue-600 bg-blue-50'
                            : 'border-gray-300'
                    }`}
                    onClick={() => {
                        console.log("Selected option",option);
                        setFieldValue(fieldName, JSON.stringify(option))
                    }}
                >
                    {option.label}
                </button>
            ))}

            {/* Register the field so it's included in form submission */}
            <input type="hidden" {...registerField(fieldName)} />
        </div>
    );
}