<style>
	.import-stages-list li.current {
		font-weight: bold;
	}

	.current-import {
		display: flex;
		flex-direction: row;
		gap: 20px;
		margin: 20px 0 40px 0;

		.import-stages-list {
			margin: 0;
		}

		.import-stage-details {
			flex-grow: 1;
			h1:first-child,
			h2:first-child,
			h3:first-child,
			h4:first-child,
			h5:first-child,
			h6:first-child {
				margin-top: 0;
			}
		}
	}

	.hidden {
		display: none;
	}

	.hidden.show {
		display: block;
	}
</style>
<div 
	class="wrap"
	data-wp-interactive="dataLiberation"
	data-wp-init--refresh="callbacks.startRefreshingProgress"
	data-wp-init--import-loop="callbacks.startImportLoop"
>
	<h1>Data Liberation</h1>

	<div
		data-wp-class--hidden="!state.currentImport.active"
		data-wp-router-region="current-import-details"
	>
		<h2>Current Import</h2>
		<b data-wp-text="state.currentImport.dataSource"></b>:
		<span data-wp-text="state.currentImport.fileReference"></span>

		<b>Stage:</b>
		<span data-wp-text="state.currentImport.stage"></span>

		<div class="current-import">
			<div class="import-stages">
				<ul class="import-stages-list">
					<template data-wp-each--stage="state.stages">
						<li 
							data-wp-class--current="state.isCurrentImportAtStage"
						>
							<span data-wp-text="context.stage.completed ? '☑' : '☐'"></span>
							<span data-wp-text="context.stage.label"></span>
						</li>
					</template>
				</ul>
			</div>
			<div class="import-stage-details">
				<div 
					data-wp-context='{ "stage": { "id": "#index_entities" } }'
					data-wp-class--hidden="!state.isCurrentImportAtStage"
				>
					<h3>Indexing Entities</h3>

					<table>
						<thead>
							<tr>
								<th>Entity</th>
								<th>Imported</th>
								<th>Total</th>
							</tr>
						</thead>
						<tbody>
							<!-- <template data-wp-each--entity="state.currentImport.entityCounts">
								<tr>
									<td data-wp-text="context.entity.label"></td>
									<td data-wp-text="context.entity.imported"></td>
									<td data-wp-text="context.entity.total"></td>
								</tr>
							</template> -->
						</tbody>
					</table>
				</div>

				<div 
					data-wp-context='{ "stage": { "id": "#frontload_assets" } }'
					data-wp-class--hidden="!state.isCurrentImportAtStage"
				>
					<h3>Downloading Assets</h3>
					
					<div data-wp-class--hidden="!state.currentImport.hasFrontloadingProgress">
						<progress 
							data-wp-bind--value="state.currentImport.importedEntities.download" 
							data-wp-bind--max="state.currentImport.totalEntities.download">
						</progress>
						<span data-wp-text="state.currentImport.importedEntities.download"></span> / 
						<span data-wp-text="state.currentImport.totalEntities.download"></span> Files Downloaded
						
						<h4>Downloads in progress:</h4>
						<table>
							<thead>
								<tr>
									<th>File</th>
									<th>Progress</th>
								</tr>
							</thead>
							<tbody>
								<template data-wp-each--download="state.currentImport.frontloadingProgress">
									<tr>
										<td><small data-wp-text="context.download.url"></small></td>
										<td>
											<progress 
												data-wp-bind--value="context.download.received"
												data-wp-bind--max="context.download.total">
											</progress>
											<span data-wp-text="context.download.received"></span> / 
											<span data-wp-text="context.download.total"></span>
										</td>
									</tr>
								</template>
							</tbody>
						</table>
					</div>

					<div data-wp-class--hidden="!state.currentImport.hasFrontloadingPlaceholders">
						<table>
							<thead>
								<tr>
									<th>ID</th>
									<th>File</th>
									<th>Retries</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<template data-wp-each--item="state.currentImport.frontloadingPlaceholders">
									<tr>
										<td data-wp-text="context.item.ID"></td>
										<td data-wp-text="context.item.meta.current_url"></td>
										<td data-wp-text="context.item.meta.attempts"></td>
										<td>
											<span data-wp-text="context.item.post_status"></span>
											<span data-wp-text="context.item.meta.last_error"></span>
											<div data-wp-context='{ "item": context.item }'>
												<div
													class="hidden"
													data-wp-class--show="state.frontloadingFailed"
												>
													<button 
														data-wp-on--click="actions.retryDownload"
														data-wp-bind--data-post-id="context.item.ID">
														Retry
													</button>
													<button 
														data-wp-on--click="actions.ignoreDownload"
														data-wp-bind--data-post-id="context.item.ID">
														Ignore
													</button>
													<button 
														data-wp-on--click="actions.changeDownloadUrl"
														data-wp-bind--data-post-id="context.item.ID">
														Use another URL
													</button>
													<!-- @TODO -->
													<!-- <button>Remove from the imported content</button> -->
													<!-- <button>Generate a placeholder image</button> -->
												</div>
											</div>
										</td>
									</tr>
								</template>
							</tbody>
						</table>
					</div>
				</div>

				<div 
					data-wp-context='{ "stage": { "id": "#import_entities" } }'
					data-wp-class--hidden="!state.isCurrentImportAtStage"
				>
					<h3>Importing Entities</h3>

					<table>
						<thead>
							<tr>
								<th>Entity</th>
								<th>Imported</th>
								<th>Total</th>
							</tr>
						</thead>
						<tbody>
							<template data-wp-each--entity="state.currentImport.entityCounts">
								<tr>
									<td data-wp-text="context.entity.label"></td>
									<td data-wp-text="context.entity.imported"></td>
									<td data-wp-text="context.entity.total"></td>
								</tr>
							</template>
						</tbody>
					</table>
				</div>

				<div data-wp-context='{ "stage": { "id": "#finished" } }'
					data-wp-class--hidden="!state.isCurrentImportAtStage"
				>
					<p>Import finished!</p>
					<a href="#" 
						class="button"
						data-wp-on--click="actions.archiveImport">
						Archive the importing session
					</a>
				</div>

				<div data-wp-context='{ "stage": { "id": "#finished" } }'
					data-wp-class--hidden="state.isCurrentImportAtStage"
				>
					<a href="#" 
						class="button"
						data-wp-on--click="actions.archiveImport">
						Stop importing and archive the session
					</a>
				</div>
			</div>
		</div>
	</div>

	<div class="new-import-form">
		<h2>Start a new import session</h2>
		<form
			method="post"
			enctype="multipart/form-data"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		>
			<?php wp_nonce_field( 'data_liberation_import' ); ?>
			<input type="hidden" name="action" value="data_liberation_import">
			<table class="form-table">
				<tr>
					<th scope="row">Import Type</th>
					<td>
						<label data-wp-context='{ "importType": "wxr_file" }'>
							<input type="radio" name="data_source" value="wxr_file" checked
								data-wp-bind--checked="state.isImportTypeSelected"
								data-wp-on--change="actions.setImportType">
							Upload WXR File
						</label><br>
						<label data-wp-context='{ "importType": "wxr_url" }'>
							<input type="radio" name="data_source" value="wxr_url"
								data-wp-bind--checked="state.isImportTypeSelected"
								data-wp-on--change="actions.setImportType">
							WXR File URL
						</label><br>
						<label data-wp-context='{ "importType": "markdown_zip" }'>
							<input type="radio" name="data_source" value="markdown_zip"
								data-wp-bind--checked="state.isImportTypeSelected"
								data-wp-on--change="actions.setImportType">
							Markdown ZIP Archive
						</label>
					</td>
				</tr>

				<tr data-wp-context='{ "importType": "wxr_file" }'
					data-wp-class--hidden="!state.isImportTypeSelected">
					<th scope="row">WXR File</th>
					<td>
						<input type="file" name="wxr_file" accept=".xml">
						<p class="description">Upload a WordPress eXtended RSS (WXR) file</p>
					</td>
				</tr>

				<tr data-wp-context='{ "importType": "wxr_url" }'
					data-wp-class--hidden="!state.isImportTypeSelected">
					<th scope="row">WXR URL</th>
					<td>
						<input type="url" name="wxr_url" class="regular-text">
						<p class="description">Enter the URL of a WXR file</p>
					</td>
				</tr>

				<tr data-wp-context='{ "importType": "markdown_zip" }'
					data-wp-class--hidden="!state.isImportTypeSelected">
					<th scope="row">Markdown ZIP</th>
					<td>
						<input type="file" name="markdown_zip" accept=".zip">
						<p class="description">Upload a ZIP file containing markdown files</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary">Start Import</button>
			</p>
		</form>
	</div>

	<h2>Previous Import Sessions</h2>

	<table class="form-table">
		<tr>
			<th scope="row">Date</th>
			<th scope="row">Data source</th>
			<th scope="row">Time taken</th>
			<th scope="row">Entities imported</th>
			<th scope="row">Total entities</th>
			<th scope="row">Status</th>
		</tr>
		<template data-wp-each--import="state.importHistory.entities">
			<tr>
				<td data-wp-text="context.import.date"></td>
				<td data-wp-text="context.import.dataSource"></td>
				<td data-wp-text="context.import.timeTaken"></td>
				<td data-wp-text="context.import.entitiesImported"></td>
				<td data-wp-text="context.import.totalEntities"></td>
				<td data-wp-text="context.import.status"></td>
			</tr>
		</template>
		<tr data-wp-class--hidden="state.importHistory.numEntities">
			<td colspan="6">No import sessions found</td>
		</tr>
	</table>
</div>