# Register the callback but render nothing: the tags are built and never reach the head.
s/\$hook->add_html\(\$hook->renderer->render\(\$tags\)\);/return;/;
