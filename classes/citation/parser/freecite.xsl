<?xml version='1.0' encoding='utf-8'?>
<!--
  * parscit.xsl
  *
  * Copyright (c) 2003-2009 John Willinsky
  * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
  *
  * Simple mapping from Freecite web service into
  * a flat XML for conversion into a PHP array
  *
  * $Id$
  -->

<xsl:transform version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:ctx="info:ofi/fmt:xml:xsd:ctx"
		exclude-result-prefixes="xsl ctx">

<xsl:strip-space elements="*"/>

<xsl:template match="/citations">
	<citation>

		<xsl:choose>
			<!-- get elements from contextobject if it exists -->
			<xsl:when test="ctx:context-objects/ctx:context-object/ctx:referent/ctx:metadata-by-val/ctx:metadata">
				<xsl:apply-templates select="ctx:context-objects/ctx:context-object/ctx:referent/ctx:metadata-by-val/ctx:metadata/*/*"/>
			</xsl:when>
			
			<xsl:otherwise>
				<!-- get any additional elements from citation -->
				<xsl:apply-templates select="citation/authors/*"/>
				<xsl:apply-templates select="citation/*[local-name(.) != 'authors']"/>
			</xsl:otherwise>
		</xsl:choose>

		<xsl:if test="ctx:context-objects/ctx:context-object/ctx:referent/ctx:metadata-by-val/ctx:metadata/dissertation">
			<genre>dissertation</genre>
		</xsl:if>

	</citation>
</xsl:template>

<!-- Authors -->
<xsl:template match="*[local-name() = 'au'] | *[local-name() = 'corp']">
	<author><xsl:value-of select="."/></author>		
</xsl:template>

<!-- Book title -->
<xsl:template match="*[local-name() = 'btitle']">
	<bookTitle><xsl:value-of select="."/></bookTitle>		
</xsl:template>

<!-- Journal/conference title -->
<xsl:template match="*[local-name() = 'stitle'] | *[local-name() = 'jtitle']">
	<journalTitle><xsl:value-of select="."/></journalTitle>		
</xsl:template>

<!-- Article title -->
<xsl:template match="*[local-name() = 'title'] | *[local-name() = 'atitle']">
	<articleTitle><xsl:value-of select="."/></articleTitle>		
</xsl:template>

<!-- Article title -->
<xsl:template match="*[local-name() = 'year'] | *[local-name() = 'date']">
	<issuedDate><xsl:value-of select="."/></issuedDate>		
</xsl:template>

<!-- Location -->
<xsl:template match="*[local-name() = 'place'] | *[local-name() = 'location']">
	<place><xsl:value-of select="."/></place>
</xsl:template>

<!-- Publisher -->
<xsl:template match="*[local-name() = 'pub'] | *[local-name() = 'inst']">
	<publisher><xsl:value-of select="."/></publisher>
</xsl:template>

<!-- Pages -->
<xsl:template match="*[local-name() = 'pages']">
	<firstPage><xsl:value-of select="substring-before(., '--')"/></firstPage>
	<lastPage><xsl:value-of select="substring-after(., '--')"/></lastPage>
</xsl:template>
<xsl:template match="*[local-name() = 'spage']">
	<firstPage><xsl:value-of select="."/></firstPage>		
</xsl:template>
<xsl:template match="*[local-name() = 'epage']">
	<lastPage><xsl:value-of select="."/></lastPage>		
</xsl:template>

<!-- Issue: We cannot interpret number or quarter, so let's save them as issue -->
<xsl:template match="*[local-name() = 'number'] | *[local-name() = 'quarter']">
	<issue><xsl:value-of select="."/></issue>
</xsl:template>

<!-- Comments -->
<xsl:template match="note | *[local-name() = 'degree']">
	<comment><xsl:value-of select="."/></comment>
</xsl:template>

<!-- copy element and value -->
<xsl:template match="*">
	<xsl:element name="{local-name()}"><xsl:value-of select="."/></xsl:element>
</xsl:template>

</xsl:transform>