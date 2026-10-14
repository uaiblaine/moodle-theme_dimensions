# Forget enrol_paypal's rule: a course offered only through paypal reads none on the card while its page
# takes the payment.
s/case 'paypal':/case 'paypal-mutated':/;
