# Show a failure in the service's own words. The refusal speaks the request's language, not the
# page's, and hands a visitor a sentence about a web service. Four edits so the build still changes:
# the failure is passed, taken, documented and printed.
s/fallBack\(fallbackUrl\(page, append\)\);/fallBack(fallbackUrl(page, append), error);/;
s/^const fallBack = async\(url\) => \{$/const fallBack = async(url, error) => {/m;
s/( \* \@param \{URL\} url The address of the request that failed\.\n)/$1 * \@param {Error} error The failure.\n/;
s/notice\.textContent = text;/notice.textContent = error.message || text;/;
