# Read the panel's period off the address raw: a hand-edited pair given backwards then reaches a
# web service that refuses it, and no panel of that page opens.
s/const range = calendarPeriod\(params\.get\(Listing\.PARAM\.from\), params\.get\(Listing\.PARAM\.to\)\);/const range = {from: params.get(Listing.PARAM.from) || '', to: params.get(Listing.PARAM.to) || ''};/;
