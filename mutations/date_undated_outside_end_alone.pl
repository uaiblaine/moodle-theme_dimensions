# Leave out the clause that keeps an undated course outside a period when the period has an end
# alone: "starting on or before the 19th" then reads as true of every course that starts on no day.
s/if \(\$criteria->fromtime !== null \|\| \$criteria->totime !== null\) \{/if (\$criteria->fromtime !== null) {/;
