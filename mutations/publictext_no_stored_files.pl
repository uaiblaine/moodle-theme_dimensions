# Admit every address into the public page text. A browser can still put a picture into that
# editor with a paste or a drag-and-drop, and TinyMCE leaves it inline once its own upload fails:
# data: in the value that is submitted, blob: in the live DOM. Neither is in core's HTMLPurifier
# scheme list, so the public page drops the element while the row keeps the base64 - a manager
# who published a picture, sees it in the editor, and is the only person who ever will.
s/return preg_match\(self::UNSERVABLE_ADDRESS, \$html\) === 1;/return false;/;
