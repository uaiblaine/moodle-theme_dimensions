# Read the panel's period off the address raw: a hand-edited pair given backwards then reaches a
# web service that refuses it, and no panel of that page opens.
s/const period = DateRange\.period\(params\.get\(PARAM\.from\), params\.get\(PARAM\.to\)\);/const period = {from: params.get(PARAM.from) || '', to: params.get(PARAM.to) || ''};/;
