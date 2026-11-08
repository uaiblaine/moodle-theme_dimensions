# Treat the source as never the fallback image: a site that chose Boost Union's fallback image gets the drawn cover instead.
s/if \(boost_union::image_source\(\$this->page\) !== boost_union::SOURCE_FALLBACK\) \{/if (true) {/;
