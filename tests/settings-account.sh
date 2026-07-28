#!/bin/sh

set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$repo_root"

if rg -q 'class="tc-user"|data-icon="user"' components/head.php; then
    echo "the top bar must not render a separate user entry" >&2
    exit 1
fi

if ! rg -q "components/modals/login\.php" components/modals/setting.php; then
    echo "settings must include the existing account functionality" >&2
    exit 1
fi

for template in index.php archive.php archive-page.php post.php page.php edit-page.php; do
    if rg -q "components/modals/login\.php" "$template"; then
        echo "$template must not render a separate login modal" >&2
        exit 1
    fi
done

if rg -q 'login-modal|loginModalShow' components/modals/login.php edit-page.php; then
    echo "account interactions must use the settings modal" >&2
    exit 1
fi

if ! rg -q '管理后台' components/modals/login.php || ! rg -q '退出登录' components/modals/login.php; then
    echo "the merged account area must retain logged-in actions" >&2
    exit 1
fi

if ! rg -q 'loginAction' components/modals/login.php; then
    echo "the merged account area must retain the login form" >&2
    exit 1
fi

if rg -q --pcre2 '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]' components/modals/setting.php components/modals/login.php; then
    echo "settings and account UI must not use emoji icons" >&2
    exit 1
fi

if rg -q -A 4 'setting-modal-title::before' assets/css/icefox.css | rg -q --pcre2 '[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]'; then
    echo "the settings title must not use a generated emoji icon" >&2
    exit 1
fi

echo "Account functionality is consolidated into settings"
