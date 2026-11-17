# Mark the menus as ARIA menus: a promise of a keyboard model that only a script can keep.
s/(<div class="dim-navpill-menu" id="\{\{menuid\}\}")/$1 role="menu"/g;
