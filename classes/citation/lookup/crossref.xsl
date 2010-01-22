<?xml version="1.0"?>
<!--
  * crossref.xsl
  *
  * Copyright (c) 2000-2010 John Willinsky
  * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
  *
  * Crosswalk from CrossRef XML to PKP citation elements
  *
  * $Id$
  -->

<xsl:transform version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	exclude-result-prefixes="xsl">

<xsl:output omit-xml-declaration="yes"/>

<xsl:strip-space elements="*"/>

<!--============================================
	START TRANSFORMATION AT THE ROOT NODE
==============================================-->
<xsl:template match="/">
	<citation>
		<xsl:apply-templates select="doi_records/doi_record/crossref/*"/>
	</citation>
</xsl:template>

<!--============================================
	JOURNAL METADATA
==============================================-->

<xsl:template match="journal">
	<!-- Genre -->
	<genre>article</genre>
	
	<!-- Journal title -->
	<xsl:if test="journal_metadata/full_title">
		<journalTitle>
			<xsl:choose>
				<xsl:when test="journal_metadata/abbrev_title != ''">
					<xsl:value-of select="journal_metadata/abbrev_title"/>
				</xsl:when>
				<xsl:otherwise>
					<xsl:value-of select="journal_metadata/full_title"/>
				</xsl:otherwise>
			</xsl:choose>
		</journalTitle>
	</xsl:if>
	
	<!-- Issue -->
	<xsl:if test="journal_issue/issue">
		<issue><xsl:value-of select="journal_issue/issue"/></issue>
	</xsl:if>
	
	<!-- Volume -->
	<xsl:if test="journal_issue/journal_volume/volume">
		<volume><xsl:value-of select="journal_issue/journal_volume/volume"/></volume>
	</xsl:if>
	
	<!-- Article Title -->
	<xsl:if test="journal_article/titles/title">
		<articleTitle><xsl:value-of select="journal_article/titles/title"/></articleTitle>
	</xsl:if>

	<!-- Authors -->	
	<xsl:apply-templates select="journal_article/contributors/person_name"/>
	
	<!-- Issued Date -->
	<xsl:apply-templates select="journal_article/publication_date"/>
	
	<!-- Pages -->
	<xsl:apply-templates select="journal_article/pages"/>

	<!-- Identifiers -->
	<xsl:apply-templates select="journal_article/doi_data"/>
	
	<!-- 
	More identifier info: ISSN, publisher eg SICI
	 -->
</xsl:template>


<!--============================================
	BOOK METADATA
==============================================-->

<xsl:template match="book">
	<!-- Genre -->
	<genre>book</genre>
	
	<!-- Book Title -->
	<xsl:if test="book_metadata/titles/title">
		<bookTitle><xsl:value-of select="book_metadata/titles/title"/></bookTitle>
	</xsl:if>

	<!-- Authors -->	
	<xsl:apply-templates select="book_metadata/contributors/person_name"/>
	
	<!-- Issued Date -->
	<xsl:apply-templates select="book_metadata/publication_date"/>
	
	<!-- Edition -->
	<xsl:if test="book_metadata/edition_number">
		<edition><xsl:value-of select="book_metadata/edition_number"/></edition>
	</xsl:if>
	
	<!-- ISBN -->
	<xsl:if test="book_metadata/isbn">
		<isbn><xsl:value-of select="book_metadata/isbn"/></isbn>
	</xsl:if>
	
	<!-- Publisher -->
	<xsl:apply-templates select="book_metadata/publisher"/>
	
	<!-- Identifiers -->
	<xsl:apply-templates select="book_metadata/doi_data"/>
</xsl:template>

<!--============================================
	CONFERENCE METADATA
==============================================-->

<xsl:template match="conference">
	<!-- Genre -->
	<genre>proceeding</genre>
	
	<!-- Conference title -->
	<xsl:if test="proceedings_metadata/proceedings_title">
		<journalTitle>
			<xsl:value-of select="proceedings_metadata/proceedings_title"/>
		</journalTitle>
	</xsl:if>
	
	<!-- ISBN -->
	<xsl:if test="proceedings_metadata/isbn">
		<isbn><xsl:value-of select="proceedings_metadata/isbn"/></isbn>
	</xsl:if>
	
	<!-- Publisher -->
	<xsl:apply-templates select="proceedings_metadata/publisher"/>
	
	<!-- Article Title -->
	<xsl:if test="conference_paper/titles/title">
		<articleTitle><xsl:value-of select="conference_paper/titles/title"/></articleTitle>
	</xsl:if>

	<!-- Authors -->	
	<xsl:apply-templates select="conference_paper/contributors/person_name"/>
	
	<!-- Issued Date -->
	<xsl:apply-templates select="conference_paper/publication_date"/>
	
	<!-- Pages -->
	<xsl:apply-templates select="conference_paper/pages"/>

	<!-- Identifiers -->
	<xsl:apply-templates select="conference_paper/doi_data"/>
</xsl:template>

<!--============================================
	COMMON ELEMENTS
==============================================-->

<!-- Authors:
		We have to concatenate surname and given name so that
		we can parse out initials later which are in the given name -->
<xsl:template match="person_name">
	<author>
		<xsl:value-of select="surname"/>, <xsl:value-of select="given_name"/>
	</author>
</xsl:template>
	
<!-- Issued Date -->
<xsl:template match="publication_date">
	<issuedDate>
		<xsl:if test="year"><xsl:value-of select="year"/><xsl:if test="month">-<xsl:value-of select="month"/><xsl:if test="day">-<xsl:value-of select="day"/></xsl:if></xsl:if></xsl:if>
	</issuedDate>
</xsl:template>

<!-- Pages -->
<xsl:template match="pages">
	<!-- First Page -->
	<xsl:if test="first_page">
		<firstPage><xsl:value-of select="first_page"/></firstPage>
	</xsl:if>
	
	<!-- Last Page -->
	<xsl:if test="last_page">
		<lastPage><xsl:value-of select="last_page"/></lastPage>
	</xsl:if>
</xsl:template>

<!-- Identifiers -->
<xsl:template match="doi_data">
	<!-- DOI -->
	<xsl:if test="doi">
		<doi><xsl:value-of select="doi"/></doi>
	</xsl:if>
	
	<!-- URL; NB: may not be Open Access / full text -->
	<xsl:if test="resource">
		<url><xsl:value-of select="resource"/></url>
	</xsl:if>
</xsl:template>

<!-- Publisher -->
<xsl:template match="publisher">
	<xsl:if test="publisher_name">
		<publisher><xsl:value-of select="publisher_name"/></publisher>
	</xsl:if>
	
	<!-- Publisher Location -->
	<xsl:if test="publisher_place">
		<place><xsl:value-of select="publisher_place"/></place>
	</xsl:if>
</xsl:template>
	
</xsl:transform>
