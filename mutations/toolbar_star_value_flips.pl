# Post fav=1 whatever the toggle's state: without JavaScript a pressed toggle can never be switched off.
s/(<button type="submit" class="dim-toolbar__star" name="\{\{name\}\}" value=")\{\{value\}\}"/${1}1"/;
