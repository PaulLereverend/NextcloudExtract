/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import path from 'path'
import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
    'init': path.join(__dirname, 'src', 'init.ts'),
    'extractAction': path.join(__dirname, 'src/actions', 'extract-action.ts'),
}, {
    inlineCSS: false,
})