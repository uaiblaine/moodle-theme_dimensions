# The renderer always draws core's table.
s/if \(!class_report_view::table_requested\(\)\) \{/if (false) {/;
