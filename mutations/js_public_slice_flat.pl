# Ask a merely listed area's slice in the grouped organisation. The service refuses a slice that is not
# flat (rows::require_wellformed_slice()), so every such poster falls back to a page load.
s/const sort = psort === PSORT_NAME \? SORT\.name : SORT\.recent;/const sort = psort === PSORT_NAME ? SORT.name : 'category';/;
