import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { Node, View, Permission, Folder, FileAction } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
// import type VueRouter from 'vue-router'
import FolderZipSvg from '@mdi/svg/svg/folder-zip.svg?raw'

export const action = new FileAction({
	id: 'extract',
	displayName: () => t('extract', 'Extract here'),
	iconSvgInline: () => FolderZipSvg,
	// Only works on single files
	enabled(nodes: Node[]) {
		// Only works on single node
		if (nodes.length !== 1) {
			return false
		}

		if (nodes[0].attributes.getcontenttype === 'application/zip'
			|| nodes[0].attributes.getcontenttype === 'application/x-tar'
			|| nodes[0].attributes.getcontenttype === 'application/gzip'
			|| nodes[0].attributes.getcontenttype === 'application/x-rar-compressed'
			|| nodes[0].attributes.getcontenttype === 'application/x-7z-compressed'
			|| nodes[0].attributes.getcontenttype === 'application/x-deb'
			|| nodes[0].attributes.getcontenttype === 'application/x-bzip2'
		) {
			return (nodes[0].permissions & Permission.UPDATE) !== 0
		}

		return false
	},
	async exec(node: Node, view: View, dir: string) {
		var data = {
			nameOfFile: node.attributes.basename,
			directory: dir,
			external: node.attributes["mount-type"].startsWith("external") ? 1 : 0,
			mime: node.attributes.mime,
		};
		const url = generateUrl('/apps/extract/ajax/extract.php')
		axios.post(url, data)
			.then(resp => resp.data)
			.then(data => {
				// We need the extracted folder so we can emit it in the next step.
				// Just like it's done when a new folder is created.
				const time = data['extracted']['mtime'] * 1000
				const folder = new Folder({
					id: data['extracted']['fileId'],
					source: data['extracted']['source'],
					root: data['extracted']['root'],
					owner: data['extracted']['owner'],
					permissions: data['extracted']['permissions'],
					mtime: new Date(time),
					// Include mount-type from parent folder as this is inherited
					attributes: {
						'mount-type': data['extracted']['mount-type'],
						'owner-id': data['extracted']['owner'],
						'owner-display-name': data['extracted']['owner-display-name'],
					},
				})

				emit('files:node:created', folder)

				window.OCP.Files.Router.goToRoute(
					null, // use default route
					{ view: 'files', fileid: data['fileId'] },
					{ dir: dir },
				)
				return null

			})
			.catch(error => {
				console.log('Could not send extract request.')
				console.log(error)
			})
		return null
	},
	order: 25,
})