import { registerFileAction } from '@nextcloud/files'

import { action as extractAction } from './actions/extract-action'

registerFileAction(extractAction)