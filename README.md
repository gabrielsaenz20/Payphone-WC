# Payphone WC Modal

WordPress plugin that integrates **Payphone Cajita de Pagos** into a WooCommerce checkout page as a modal overlay — customers pay without leaving the checkout page.

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| PHP | 7.4+ |
| WooCommerce | 5.0+ |

---

## Installation

1. Go to the **Releases** tab (or download the repository as a ZIP from GitHub).
2. In your WordPress dashboard go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**, then **Activate**.

---

## Configuration

1. Go to **WooCommerce → Settings → Payments**.
2. Click **Payphone** to open the gateway settings.
3. Fill in the following fields:

| Field | Description |
|---|---|
| **Habilitar** | Toggle the payment method on/off |
| **Título** | Label shown to the customer on checkout (default: "Payphone") |
| **Descripción** | Short description shown under the payment method |
| **Token de API** | Bearer token from [pay.payphone.app](https://pay.payphone.app) developer console |
| **Store ID** | Your Payphone store ID (`storeId`) |
| **Referencia del pago** | Payment description shown inside Payphone (order number is appended automatically) |
| **Color del botón** | Background colour of the Payphone payment button |

4. Click **Save changes**.

---

## How it works

```
Customer fills checkout → clicks "Place Order"
        ↓
WooCommerce creates the order (status: Pending)
        ↓
Plugin stores payment data in WC session
        ↓
Our JavaScript opens the Payphone modal
        ↓
Customer enters card / wallet details inside the modal
        ↓
PPaymentButtonBox fires functionResult callback
        ↓
Plugin confirms payment with Payphone server API
        ↓
Order marked as Paid → customer redirected to Order Received page
```

---

## File structure

```
payphone-wc-modal.php          ← Main plugin file (WordPress entry point)
includes/
  class-payphone-gateway.php   ← WooCommerce payment gateway class
assets/
  css/
    payphone-modal.css         ← Modal overlay styles
  js/
    payphone-checkout.js       ← Checkout interception & modal logic
```

---

## Notes

- **Tax amounts** are passed to Payphone as whole-cent integers (multiply by 100).  
  The entire pre-tax total is sent as `amountWithoutTax`; the IVA amount as `tax`.  
  Adjust `class-payphone-gateway.php → process_payment()` if your store has mixed
  IVA-0 / IVA-15 products and you need a precise taxable-base breakdown.

- The plugin is compatible with WooCommerce **High-Performance Order Storage (HPOS)**.

- Blocks-based checkout (WooCommerce Blocks) is not yet supported; the classic
  WooCommerce checkout is required.

---

## License

GPL-2.0-or-later