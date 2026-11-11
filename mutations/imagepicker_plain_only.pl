# Replace any file manager, a picker included, instead of core's plain one alone.
s/if \(get_class\(\$old\) !== \\MoodleQuickForm_filemanager::class\) \{/if (!\$old instanceof \\MoodleQuickForm_filemanager) {/;
