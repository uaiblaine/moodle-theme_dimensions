# Read the filter Bootstrap 5.3.4 no longer declares: the dialogue's cross stays dark on its dark disc.
s/\@include btn-close-white\(\);\n(\s*)filter: var\(--bs-btn-close-filter\);/filter: var(--bs-btn-close-white-filter);/;
