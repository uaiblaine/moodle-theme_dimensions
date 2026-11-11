# Put back only the bar's header actions after the page header: what the parent appended is lost.
s/array_slice\(\$before, 0, \$taken\), \$this->page->get_header_actions\(\)\)\);/array_slice(\$before, 0, \$taken)));/;
