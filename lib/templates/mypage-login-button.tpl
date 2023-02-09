<script src="https://static-fe.payments-amazon.com/checkout.js"></script>
<script>
 amazon.Pay.renderButton('#AmazonLoginButton', {
     // set checkout environment
     merchantId: '<!--{$smarty.env.AMAZONPAY_MERCHANT_ID|h}-->',
     ledgerCurrency: 'JPY',
     sandbox: Boolean(<!--{$smarty.env.AMAZONPAY_SANDBOX|h}-->),
     // customize the buyer experience
     checkoutLanguage: 'ja_JP',
     productType: 'SignIn',
     placement: 'Other',
     buttonColor: 'Gold',
     // configure Create Checkout Session request
     signInConfig: {
         payloadJSON: '<!--{$payload|json_encode}-->', // string generated in step 2
         signature: '<!--{$signature}-->',
         publicKeyId: '<!--{$smarty.env.AMAZONPAY_PUBLIC_KEY_ID|h}-->'
     }
 });
</script>
