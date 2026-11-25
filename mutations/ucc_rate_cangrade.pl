# Draw the course summary's Rate for every viewer: the section around the primary button reads the learner (always
# there) instead of core's cangrade, so a learner is offered a button whose dialogue core would refuse.
s/\{\{#cangrade\}\}(\n\s*<div class="dim-comp-ucc-primary">.*?<\/div>\n\s*)\{\{\/cangrade\}\}/{{#user}}$1\{\{\/user}}/s;
