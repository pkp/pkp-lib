<?xml version='1.0' encoding='utf-8'?>
<!--
  * parscit.xsl
  *
  * Copyright (c) 2000-2010 John Willinsky
  * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
  *
  * Simple mapping from Parscit web service into
  * a flat XML for conversion into a PHP array
  *
  * $Id$
  -->

<xsl:transform version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		exclude-result-prefixes="xsl">

<xsl:strip-space elements="*"/>

<xsl:template match="/citationList">
	<citation>
		<xsl:apply-templates select="citation/*"/>
	</citation>
</xsl:template>

<!-- Genre -->
<xsl:template match="*[local-name() = 'genre']">
	<genre>
		<xsl:choose>
			<!-- map to interal options -->
			<xsl:when test=". = 'article'">journal</xsl:when>
			<xsl:when test=". = 'proceeding'">proceedings</xsl:when>
			<xsl:otherwise>other</xsl:otherwise>
		</xsl:choose>
	</genre>		
</xsl:template>

<!-- Authors -->
<xsl:template match="authors">
	<xsl:copy-of select="*"/>
</xsl:template>

<!-- Article title -->
<xsl:template match="title">
	<articleTitle><xsl:value-of select="."/></articleTitle>
</xsl:template>

<!-- Book title -->
<xsl:template match="booktitle">
	<bookTitle><xsl:value-of select="."/></bookTitle>		
</xsl:template>

<!-- Journal title -->
<xsl:template match="journal">
	<journalTitle><xsl:value-of select="."/></journalTitle>		
</xsl:template>

<!-- Date -->
<xsl:template match="date">
	<issuedDate><xsl:value-of select="."/></issuedDate>
</xsl:template>

<!-- Location -->
<xsl:template match="location">
	<place><xsl:value-of select="."/></place>
</xsl:template>

<!-- Pages -->
<xsl:template match="*[local-name() = 'pages']">
	<firstPage><xsl:value-of select="substring-before(., '--')"/></firstPage>
	<lastPage><xsl:value-of select="substring-after(., '--')"/></lastPage>
</xsl:template>

<!-- Comments -->
<xsl:template match="note | notes | tech">
	<comment><xsl:value-of select="."/></comment>
</xsl:template>

<!-- copy element and value -->
<xsl:template match="*">
	<xsl:element name="{local-name()}"><xsl:value-of select="."/></xsl:element>
</xsl:template>

</xsl:transform>
