<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$appRoot = '../';
$pageTitle = 'Deposit';
$pageDescription = 'Submit a deposit for Selam agent verification.';
$activeNav = 'wallet';

require __DIR__ . '/../includes/app_header.php';
?>

<div class="deposit-page">

    <div class="deposit-steps mb-4">

        <div class="deposit-step active" id="stepIndicator1">
            <span>1</span>
            <small>Amount</small>
        </div>

        <div class="deposit-step-line"></div>

        <div class="deposit-step" id="stepIndicator2">
            <span>2</span>
            <small>Payment</small>
        </div>

        <div class="deposit-step-line"></div>

        <div class="deposit-step" id="stepIndicator3">
            <span>3</span>
            <small>Submit</small>
        </div>

    </div>


    <section class="data-panel deposit-section" id="depositStep1">

        <div class="data-panel-header">

            <div>
                <h2>Deposit</h2>

                <p class="muted mb-0">
                    Enter the amount you want to deposit.
                </p>
            </div>

        </div>

        <div class="p-4">

            <label class="form-label" for="depositAmount">
                Deposit amount
            </label>

            <div class="input-group mb-3">

                <input
                    type="number"
                    class="form-control"
                    id="depositAmount"
                    min="1"
                    step="0.01"
                    placeholder="0.00"
                    autocomplete="off"
                >

                <span class="input-group-text">
                    ETB
                </span>

            </div>

            <div
                class="alert alert-danger d-none"
                id="amountError"
            ></div>

            <button
                type="button"
                class="btn btn-primary w-100"
                id="continueDeposit"
            >
                Continue
                <i class="bi bi-arrow-right"></i>
            </button>

        </div>

    </section>


    <section
        class="data-panel deposit-section d-none"
        id="depositStep2"
    >

        <div class="data-panel-header">

            <div>

                <h2>Payment account</h2>

                <p class="muted mb-0">
                    Send the exact amount to the Selam account below.
                </p>

            </div>

        </div>

        <div class="p-4">

            <div class="deposit-amount-card">

                <span>
                    Amount to pay
                </span>

                <strong>
                    <span id="displayAmount">0.00</span>
                    ETB
                </strong>

            </div>


            <div class="payment-account-card">

                <div class="payment-account-header">

                    <span class="muted small">
                        Provider
                    </span>

                    <h4 id="paymentProvider">
                        Loading...
                    </h4>

                </div>


                <div class="payment-account-row">

                    <div>

                        <span class="muted small">
                            Selam receiving account
                        </span>

                        <strong id="paymentAccount">
                            Loading...
                        </strong>

                    </div>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-light"
                        id="copyAccount"
                    >
                        <i class="bi bi-copy"></i>
                        Copy
                    </button>

                </div>


                <div class="payment-account-row">

                    <div>

                        <span class="muted small">
                            Account name
                        </span>

                        <strong id="paymentAccountName">
                            Loading...
                        </strong>

                    </div>

                </div>


                <div class="payment-account-network">

                    Currency:
                    <strong id="paymentCurrency">
                        ETB
                    </strong>

                </div>

            </div>


            <div class="alert alert-warning">

                <i class="bi bi-shield-check"></i>

                Send the exact amount to the Selam receiving
                account. After payment, submit your official
                payment receipt.

            </div>


            <button
                type="button"
                class="btn btn-primary w-100 mb-2"
                id="paidButton"
            >
                <i class="bi bi-check-circle"></i>
                I have paid
            </button>


            <button
                type="button"
                class="btn btn-outline-secondary w-100"
                id="backToAmount"
            >
                Back
            </button>

        </div>

    </section>


    <section
        class="data-panel deposit-section d-none"
        id="depositStep3"
    >

        <div class="data-panel-header">

            <div>

                <h2>Submit payment</h2>

                <p class="muted mb-0">
                    Submit your official payment receipt for review.
                </p>

            </div>

        </div>


        <form
            class="p-4"
            id="proofForm"
        >

            <input
                type="hidden"
                name="amount"
                id="proofAmount"
            >

            <input
                type="hidden"
                name="payment_account_id"
                id="proofPaymentAccountId"
            >

            <input
                type="hidden"
                name="provider"
                id="proofProvider"
            >


            <div class="verification-summary mb-4">

                <div>

                    <span class="muted small">
                        Deposit amount
                    </span>

                    <strong>
                        <span id="verifyAmount">0.00</span>
                        ETB
                    </strong>

                </div>


                <div>

                    <span class="muted small">
                        Provider
                    </span>

                    <strong id="verifyProvider">
                        -
                    </strong>

                </div>

            </div>


            <div class="mb-3">

                <label
                    class="form-label"
                    for="receiptUrl"
                >
                    Payment receipt link
                </label>

                <input
                    type="url"
                    class="form-control"
                    id="receiptUrl"
                    name="receipt_url"
                    maxlength="1000"
                    placeholder="https://..."
                    autocomplete="off"
                    required
                >

                <div class="form-text">
                    Paste the official receipt link provided by your payment provider.
                </div>

            </div>


            <div class="mb-3">

                <label
                    class="form-label"
                    for="transactionReference"
                >
                    Transaction reference
                    <span class="muted">(optional)</span>
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="transactionReference"
                    name="transaction_reference"
                    maxlength="150"
                    placeholder="If shown on your receipt"
                    autocomplete="off"
                >

            </div>


            <div class="alert alert-info">

                <i class="bi bi-info-circle"></i>

                After submission, your deposit will be placed in
                <strong>pending review</strong>. Your wallet balance
                will be updated only after the payment is approved.

            </div>


            <div
                class="alert alert-danger d-none"
                id="proofError"
            ></div>


            <div
                class="alert alert-success d-none"
                id="proofSuccess"
            ></div>


            <button
                type="submit"
                class="btn btn-primary w-100"
                id="submitProof"
            >
                <i class="bi bi-send"></i>
                Submit Payment
            </button>


            <button
                type="button"
                class="btn btn-outline-secondary w-100 mt-2"
                id="backToPayment"
            >
                Back
            </button>

        </form>

    </section>

</div>


<style>

.deposit-steps {
    display: flex;
    align-items: center;
    width: 100%;
}

.deposit-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    color: var(--text-secondary);
    min-width: 55px;
}

.deposit-step span {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    font-weight: 700;
}

.deposit-step small {
    font-size: 11px;
}

.deposit-step.active {
    color: var(--primary);
}

.deposit-step.active span {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

.deposit-step-line {
    flex: 1;
    height: 1px;
    background: var(--border);
    margin: 0 8px;
}

.deposit-amount-card {
    padding: 18px;
    border-radius: 14px;
    background: var(--bg-elevated);
    margin-bottom: 18px;
}

.deposit-amount-card span {
    display: block;
    color: var(--text-secondary);
    font-size: 13px;
}

.deposit-amount-card strong {
    display: block;
    margin-top: 5px;
    font-size: 25px;
}

.payment-account-card {
    padding: 18px;
    border-radius: 14px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    margin-bottom: 18px;
}

.payment-account-header {
    margin-bottom: 18px;
}

.payment-account-header h4 {
    margin: 5px 0 0;
}

.payment-account-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 14px 0;
    border-top: 1px solid var(--border);
}

.payment-account-row strong {
    display: block;
    margin-top: 4px;
    word-break: break-word;
}

.payment-account-network {
    padding-top: 14px;
    border-top: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 13px;
}

.verification-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.verification-summary > div {
    padding: 15px;
    border-radius: 12px;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
}

.verification-summary span {
    display: block;
}

.verification-summary strong {
    display: block;
    margin-top: 5px;
    font-size: 17px;
}

@media (max-width: 480px) {

    .verification-summary {
        grid-template-columns: 1fr;
    }

    .payment-account-row {
        align-items: flex-start;
    }

}

</style>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const step1 = document.getElementById('depositStep1');
    const step2 = document.getElementById('depositStep2');
    const step3 = document.getElementById('depositStep3');

    const indicator1 = document.getElementById('stepIndicator1');
    const indicator2 = document.getElementById('stepIndicator2');
    const indicator3 = document.getElementById('stepIndicator3');

    const amountInput = document.getElementById('depositAmount');
    const amountError = document.getElementById('amountError');

    const displayAmount = document.getElementById('displayAmount');
    const proofAmount = document.getElementById('proofAmount');
    const verifyAmount = document.getElementById('verifyAmount');

    const paymentProvider =
        document.getElementById('paymentProvider');

    const paymentAccount =
        document.getElementById('paymentAccount');

    const paymentAccountName =
        document.getElementById('paymentAccountName');

    const paymentCurrency =
        document.getElementById('paymentCurrency');

    const proofPaymentAccountId =
        document.getElementById('proofPaymentAccountId');

    const proofProvider =
        document.getElementById('proofProvider');

    const verifyProvider =
        document.getElementById('verifyProvider');

    let selectedPaymentAccount = null;


    function showStep(number) {

        step1.classList.add('d-none');
        step2.classList.add('d-none');
        step3.classList.add('d-none');

        indicator1.classList.remove('active');
        indicator2.classList.remove('active');
        indicator3.classList.remove('active');

        if (number === 1) {
            step1.classList.remove('d-none');
            indicator1.classList.add('active');
        }

        if (number === 2) {
            step2.classList.remove('d-none');
            indicator2.classList.add('active');
        }

        if (number === 3) {
            step3.classList.remove('d-none');
            indicator3.classList.add('active');
        }
    }


    function parseJsonResponse(rawText) {

        try {

            return JSON.parse(rawText);

        } catch (error) {

            console.error(
                'Invalid JSON response:',
                rawText
            );

            throw new Error(
                'The server returned an invalid response.'
            );
        }
    }


    document
        .getElementById('continueDeposit')
        .addEventListener('click', async function () {

            const amount =
                parseFloat(amountInput.value);

            if (!amount || amount <= 0) {

                amountError.textContent =
                    'Please enter a valid deposit amount.';

                amountError.classList.remove('d-none');

                return;
            }

            amountError.classList.add('d-none');

            displayAmount.textContent =
                amount.toFixed(2);

            proofAmount.value =
                amount.toFixed(2);

            verifyAmount.textContent =
                amount.toFixed(2);

            paymentProvider.textContent =
                'Loading...';

            paymentAccount.textContent =
                'Loading...';

            paymentAccountName.textContent =
                'Loading...';

            paymentCurrency.textContent =
                'ETB';

            try {

                const response = await fetch(
                    '../api/payment-account.php?amount=' +
                    encodeURIComponent(amount),
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const rawText =
                    await response.text();

                const result =
                    parseJsonResponse(rawText);

                if (!response.ok || !result.success) {

                    throw new Error(
                        result.message ||
                        'Unable to load the payment account.'
                    );
                }

                selectedPaymentAccount =
                    result.payment_account;

                paymentProvider.textContent =
                    selectedPaymentAccount.provider_name ||
                    selectedPaymentAccount.provider;

                paymentAccount.textContent =
                    selectedPaymentAccount.account_number;

                paymentAccountName.textContent =
                    selectedPaymentAccount.account_name;

                paymentCurrency.textContent =
                    selectedPaymentAccount.currency || 'ETB';

                proofPaymentAccountId.value =
                    selectedPaymentAccount.id;

                proofProvider.value =
                    selectedPaymentAccount.provider;

                verifyProvider.textContent =
                    selectedPaymentAccount.provider_name ||
                    selectedPaymentAccount.provider;

                showStep(2);

            } catch (error) {

                amountError.textContent =
                    error.message ||
                    'Unable to load the payment account.';

                amountError.classList.remove('d-none');
            }

        });


    document
        .getElementById('copyAccount')
        .addEventListener('click', async function () {

            const value =
                paymentAccount.textContent.trim();

            if (
                !value ||
                value === 'Loading...'
            ) {
                return;
            }

            try {

                await navigator.clipboard.writeText(value);

                this.innerHTML =
                    '<i class="bi bi-check"></i> Copied';

                setTimeout(() => {

                    this.innerHTML =
                        '<i class="bi bi-copy"></i> Copy';

                }, 1500);

            } catch (error) {

                alert(
                    'Unable to copy the account.'
                );
            }

        });


    document
        .getElementById('paidButton')
        .addEventListener('click', function () {

            if (!selectedPaymentAccount) {

                alert(
                    'Payment account information is not available.'
                );

                return;
            }

            showStep(3);
        });


    document
        .getElementById('backToAmount')
        .addEventListener('click', function () {

            showStep(1);

        });


    document
        .getElementById('backToPayment')
        .addEventListener('click', function () {

            showStep(2);

        });


    document
        .getElementById('proofForm')
        .addEventListener('submit', async function (event) {

            event.preventDefault();

            const errorBox =
                document.getElementById('proofError');

            const successBox =
                document.getElementById('proofSuccess');

            const submitButton =
                document.getElementById('submitProof');

            errorBox.classList.add('d-none');
            successBox.classList.add('d-none');

            const receiptUrl =
                document
                    .getElementById('receiptUrl')
                    .value
                    .trim();

            if (!receiptUrl) {

                errorBox.textContent =
                    'Please enter your payment receipt link.';

                errorBox.classList.remove('d-none');

                return;
            }

            submitButton.disabled = true;

            submitButton.innerHTML =
                '<i class="bi bi-hourglass-split"></i> Submitting...';

            const formData =
                new FormData(this);

            try {

                const response = await fetch(
                    '../api/deposit-verify.php',
                    {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const rawText =
                    await response.text();

                const result =
                    parseJsonResponse(rawText);

                if (!response.ok || !result.success) {

                    throw new Error(
                        result.message ||
                        'Unable to submit the payment.'
                    );
                }

                successBox.textContent =
                    result.message ||
                    'Your payment has been submitted and is pending review.';

                successBox.classList.remove('d-none');

                submitButton.innerHTML =
                    '<i class="bi bi-check-circle"></i> Submitted';

                if (
                    result.status === 'verified' &&
                    result.redirect
                ) {

                    setTimeout(() => {

                        window.location.href =
                            result.redirect;

                    }, 1200);

                } else {

                    setTimeout(() => {

                        window.location.href =
                            '../wallet/transactions.php';

                    }, 1800);

                }

            } catch (error) {

                errorBox.textContent =
                    error.message ||
                    'Unable to submit the payment.';

                errorBox.classList.remove('d-none');

                submitButton.disabled = false;

                submitButton.innerHTML =
                    '<i class="bi bi-send"></i> Submit Payment';
            }

        });

});

</script>


<?php require __DIR__ . '/../includes/app_footer.php'; ?>