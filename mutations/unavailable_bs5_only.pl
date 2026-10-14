# Write a Bootstrap 4 spacer into the refusal page. It resolves on 5.2 through bs4-compat.scss,
# which paints a deprecation outline under themedesignermode and which Moodle 6.0 removes, so the
# page looks perfectly right today and is the class of defect no gate of the pipeline sees:
# phpcs reads PHP, the mustache lint reads structure, stylelint reads
# CSS, and not one of them reads a class name out of a template. The scanner is the only reader.
s/<p class="mb-4">/<p class="mb-4 ml-2">/;
