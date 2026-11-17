# Draw the menu items without their icon: the pix helper leaves every item of every menu.
s/(<a class="dim-navpill-menu__item"[^>]*>)\{\{#pix\}\} \{\{icon\.key\}\}, \{\{icon\.component\}\} \{\{\/pix\}\}/$1/g;
