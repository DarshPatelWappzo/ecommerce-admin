---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Build response data before returning
Build view and JSON response payloads in a local $data variable before returning. Prefer $data = []; followed by individual $data['key'] assignments, then return view('...', $data) or response()->json($data, ...). Reuse existing view-data helpers as the initial array where appropriate. Preserve response keys, status codes, and behavior.

## Document controller methods
Document each controller method with a PHPDoc description and accurate @param and @return tags where applicable. Include brief explanations on tags so Pint preserves them. Describe the actual behavior, including unimplemented placeholders, and preserve useful array-shape return annotations.
