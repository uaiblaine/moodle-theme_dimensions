# Leave a pair of dates given backwards as it arrived: the period then keeps nothing at all while
# the toolbar states a filter, and every address minted from the request carries the pair backwards.
s/if \(\$datefrom !== '' && \$dateto !== '' && strcmp\(\$datefrom, \$dateto\) > 0\) \{/if (false) {/;
