<div class="headerDiv">
		<h4>Insert Image URL</h4>
		<div class="thickSeparator"> </div>
	</div>
	<div class="rowDiv">
      <p style="margin-left: 20px; margin-top:15px; width: 400px;"> The image upload utility is only enabled for registered users.  Instead, you may enter a URL to an image you wish to include. </p>
    </div>
	<form name="source" onsubmit="saveContent();return false;" action="#">
		<input type="hidden" name="wraped" id="wraped"/>

		
		<input name="htmlSource" id="htmlSource" type="text" size="50" maxlength="80" style="margin-left:20px;">
		<br /><br />
		<div class="mceActionPanel">
			<input type="submit" name="insert" class="btn" value="Insert" id="insert" style="margin-left: 20px;" />
			<input type="button" name="cancel" class="btn" value="Cancel" onclick="tinyMCEPopup.close();" id="cancel" style="margin-left: 20px;"/>
		</div>
	</form>
</body>
</html>



