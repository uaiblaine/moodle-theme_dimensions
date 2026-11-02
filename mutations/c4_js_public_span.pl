# Write the visitor's address without its span: a week reloads as the month.
s/const target = inSpan\(publicAddress\(props\.urls\.page, monthOfSpan\(span, select\), keys, kept\), span\);/const target = publicAddress(props.urls.page, monthOfSpan(span, select), keys, kept);/;
