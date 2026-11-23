{{*
  * Copyright (C) 2010-2026, the Friendica project
  * SPDX-FileCopyrightText: 2010-2026 the Friendica project
  *
  * SPDX-License-Identifier: AGPL-3.0-or-later
  *}}
<h1>{{$title}}</h1>
<div class="settings-section">
	<div id="settings-form">
		<form action="settings/importblocks" method="post" autocomplete="off" enctype="multipart/form-data">
			<input type="hidden" name="form_security_token" value="{{$form_security_token}}">
			<input type="hidden" name="MAX_FILE_SIZE" value="{{$importcontact_maxsize}}" />
			<p id="settings-pagetype-desc">{{$importcontactblock_text}}</p>
			<p><input type="file" name="importcontactcontact-filename" /></p>

			<div class="settings-submit-wrapper">
				<input type="submit" name="importcontactblock-submit" class="importcontactblock-submit settings-submit btn btn-default"
					value="{{$importcontactblock_button}}" />
			</div>
		</form>
</div>
</div>
