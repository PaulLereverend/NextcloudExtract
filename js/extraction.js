window.addEventListener('DOMContentLoaded', () => {

	if (!OCA.Files || !OCA.Files.fileActions) {
		return;
	}

	const types = {
		zip: [ 'application/zip', ],
		rar: [ 'application/x-rar-compressed' ],
		// TAR
		//'application/x-tar', 'application/x-7z-compressed'
		other: ['application/x-tar', 'application/x-7z-compressed', 'application/x-bzip2', 'application/x-deb', 'application/x-gzip'],
	};

	for (const [type, mimeTypes] of Object.entries(types)) {
		for (const mime of mimeTypes) {
			OCA.Files.fileActions.registerAction({
				name: 'extract-' + type,
				displayName: t('extract', 'Extract here'),
				mime,
				permissions: OC.PERMISSION_UPDATE,
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				iconClass: 'icon-extract',
				actionHandler: function (filename, context) {
					var data = {
						nameOfFile: filename,
						directory: context.dir,
						external: context.fileInfoModel.attributes.mountType && context.fileInfoModel.attributes.mountType.startsWith("external") ? 1 : 0,
						type: type,
					};
					const tr = context.fileList.findFileEl(filename);
					context.fileList.showFileBusyState(tr, true);
					$.ajax({
						type: "POST",
						async: "false",
						url: OC.filePath('extract', 'ajax', 'extract.php'),
						data: data,
						success: function (response) {
							if (response.code === 1) {
								context.fileList.reload();
							} else {
								context.fileList.showFileBusyState(tr, false);
								OC.dialogs.alert(
									t('extract', response.desc),
									t('extract', 'Error extracting ' + filename)
								);
							}
						}
					});
				}
			});
		}
	}
});
