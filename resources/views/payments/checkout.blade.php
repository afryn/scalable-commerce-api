<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razorpay Test Checkout</title>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .checkout-card {
            background: white;
            padding: 35px;
            width: 380px;
            max-width: 90%;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            text-align: center;
        }

        .amount {
            font-size: 30px;
            font-weight: bold;
            margin: 20px 0;
        }

        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        button:hover {
            background: #1d4ed8;
        }

        #status {
            margin-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="checkout-card">
        <h2>Scalable Commerce</h2>
        <p>Razorpay Test Payment</p>

        <div class="amount">
            ₹{{ number_format($amount / 100, 2) }}
        </div>

        <p>Order: {{ $gatewayOrderId }}</p>

        <button id="pay-button">
            Pay Now
        </button>

        <p id="status"></p>
    </div>

    <script>
        const options = {
            key: @json($key),
            amount: @json($amount),
            currency: @json($currency),
            order_id: @json($gatewayOrderId),

            name: "Scalable Commerce",
            description: "Test Order Payment",

            handler: function(response) {
                document.getElementById("status").textContent =
                    "Payment submitted. Waiting for server confirmation.";

                console.log("Razorpay checkout response:", response);

                // Do not update payment status here.
                // The verified webhook will handle payment confirmation.
            },

            modal: {
                ondismiss: function() {
                    document.getElementById("status").textContent =
                        "Checkout closed.";
                }
            },

            theme: {
                color: "#2563eb"
            }
        };

        document.getElementById("pay-button")
            .addEventListener("click", function(event) {
                event.preventDefault();

                const razorpay = new Razorpay(options);

                razorpay.on("payment.failed", function(response) {
                    document.getElementById("status").textContent =
                        "Payment attempt failed. You can retry.";
                });

                razorpay.open();
            });
    </script>

</body>
</html>
