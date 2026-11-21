# Send the visitor's cookies with a half: a CDN may refuse to keep the answer.
s/fetch\(url, \{credentials: 'omit'/fetch(url, {credentials: 'include'/;
