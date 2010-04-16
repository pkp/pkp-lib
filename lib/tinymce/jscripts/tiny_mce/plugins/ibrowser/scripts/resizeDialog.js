// ================================================
// PHP image browser - iBrowser 
// ================================================
// iBrowser - resize dialog to content
// ================================================
// Developed: net4visions.com
// Copyright: net4visions.com
// License: GPL - see license.txt
// (c)2005 All rights reserved.
// ================================================
// Revision: 1.0                   Date: 06/14/2005
// ================================================
	function resizeDialogToContent() {		
		if (iBrowser.isMSIE) {			
			var dw = parseInt(window.dialogWidth);
			if(dw) {				
				difw = dw - this.document.body.clientWidth;
				window.dialogWidth = this.document.body.scrollWidth + difw + 'px';	
				var dh = parseInt(window.dialogHeight);				
				difh = dh - this.document.body.clientHeight;
				window.dialogHeight = this.document.body.scrollHeight + difh + 'px';				
			}
		} else if (iBrowser.isGecko) {			
			window.sizeToContent();			
			window.scrollTo(0,0);
			window.moveTo(0,0);
		}
	}
//-------------------------------------------------------------------------