# Give the native colour input a name: the browser would submit a second, unvalidated value for the setting.
s/<input type="color" class="dim-keycolour__native"/<input type="color" name="{{name}}" class="dim-keycolour__native"/;
