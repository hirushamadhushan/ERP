# Navigation smoke test

Run the local app at `http://127.0.0.1:8000`, with Google Chrome installed.
Use an authorized test account with access to Products and Business Settings.
Provide its encrypted session cookie in a private, git-ignored JSON file with
`name`, `value`, and `url` properties (the Playwright cookie format).
Never commit the cookie file.

```powershell
$env:NAVIGATION_COOKIE_FILE = 'C:\path\to\private-cookie.json'
node tests/Browser/navigation.mjs
```

The test visits product pages repeatedly, opens/closes the Units modal,
checks purchase/margin calculation, visits Settings twice, and checks product
action menus. It asserts no JavaScript errors or full document reloads after
the initial visit. It does not submit forms or change business records.
Printed timings include browser interaction/rendering; they are measurements,
not a universal performance guarantee.
