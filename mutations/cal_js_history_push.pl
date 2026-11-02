# Push every write, a change of state included.
s/        writeAddress\(inSpan\(address\(props\.urls\.page, monthOfSpan\(span, select\), next, kept\), span\), write === 'push'\);/        writeAddress(inSpan(address(props.urls.page, monthOfSpan(span, select), next, kept), span), true);/;
