# Draw the blocks without the saved state's category.
s/return page::scope_for\(state::saved\(\)\);/return [scope::for_viewer(), state::saved()];/;
