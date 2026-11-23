# Give the phone the large screen's 70vh: Popper can no longer flip a long help text to the roomier side.
s/(\@include media-breakpoint-down\(md\) \{\s*max-height: )45vh;/${1}70vh;/;
