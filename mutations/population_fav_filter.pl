# Never narrow by the favourites: the toggle is pressed, counted and remembered, and keeps every course.
s/if \(\$criteria->favourites\) \{/if (false) {/;
