# Read the period off the address raw: a hand-edited pair given backwards, which the page itself
# put in order, then reaches a web service that refuses it on the first fetch.
s/^export const periodOf = \(params\) => DateRange\.period\(params\.get\(PARAM\.from\), params\.get\(PARAM\.to\)\);$/export const periodOf = (params) => ({from: params.get(PARAM.from) || '', to: params.get(PARAM.to) || ''});/m;
