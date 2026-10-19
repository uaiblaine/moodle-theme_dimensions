# Draw the notifications as a sheet at every width.
s/(\.navbar\.fixed-top \.popover-region-notifications \{\n\s*)\@include media-breakpoint-down\(sm\) \{/$1\@include media-breakpoint-up(xs) {/;
