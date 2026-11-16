# Keep core's i/navigationitem, which Font Awesome draws as an empty box, instead of the generic icon.
s/ && \$icon->pix !== self::NO_ICON\)/)/;
