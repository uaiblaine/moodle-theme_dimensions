# Put the browser's tooltip back on the star, beside the bar's own.
s/(class="dim-toolbar__star dim-line__iconbtn"[^>]*) data-dim-tip="\{\{label\}\}"/${1} title="{{label}}" data-dim-tip="{{label}}"/;
