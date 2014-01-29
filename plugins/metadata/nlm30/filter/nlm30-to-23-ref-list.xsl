<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xlink="http://www.w3.org/1999/xlink">
	<!-- Elements to be copied unchanged (shallow copy): -->
	<xsl:template match="ref-list|ref|label|article-title|source|year|month|day|volume|issue|season|edition|series|supplement|conf-name|conf-date|conf-loc|conf-sponsor|institution|publisher-loc|publisher-name|isbn|issn|uri|comment|annotation|access-date|fpage|lpage|pub-id">
		<xsl:copy>
			<xsl:copy-of select="@*"/>
			<xsl:apply-templates/>
		</xsl:copy>
	</xsl:template>

	<!-- Elements to be copied unchanged (deep copy): -->
	<xsl:template match="person-group">
		<xsl:copy-of select="."/>
	</xsl:template>

	<!-- Elements to be changed -->
	<xsl:template match="element-citation">
		<xsl:element name="nlm-citation">
			<xsl:attribute name="citation-type">
				<xsl:choose>
					<xsl:when test="@publication-type='conf-proc'">
						<xsl:text>confproc</xsl:text>
					</xsl:when>
					<xsl:otherwise>
						<xsl:value-of select="@publication-type"/>
					</xsl:otherwise>
				</xsl:choose>
			</xsl:attribute>

			<xsl:apply-templates select="person-group"/>
			<xsl:apply-templates select="article-title"/>
			<xsl:apply-templates select="source"/>
			<xsl:apply-templates select="year"/>
			<xsl:apply-templates select="month"/>
			<xsl:apply-templates select="day"/>
			<xsl:apply-templates select="volume"/>
			<xsl:apply-templates select="issue"/>
			<xsl:apply-templates select="season"/>
			<xsl:apply-templates select="edition"/>
			<xsl:apply-templates select="series"/>
			<xsl:apply-templates select="supplement"/>
			<xsl:apply-templates select="conf-name"/>
			<xsl:apply-templates select="conf-date"/>
			<xsl:apply-templates select="conf-loc"/>
			<xsl:apply-templates select="conf-sponsor"/>
			<xsl:apply-templates select="institution"/>
			<xsl:apply-templates select="publisher-loc"/>
			<xsl:apply-templates select="publisher-name"/>
			<xsl:apply-templates select="isbn"/>
			<xsl:apply-templates select="issn"/>
			<xsl:apply-templates select="comment"/>
			<xsl:apply-templates select="annotation"/>
			<xsl:apply-templates select="access-date"/>
			<xsl:apply-templates select="fpage"/>
			<xsl:apply-templates select="lpage"/>
			<xsl:apply-templates select="uri"/>
			<xsl:apply-templates select="pub-id"/>
		</xsl:element>
	</xsl:template>

	<xsl:template match="uri">
		<xsl:element name="comment">
			<xsl:element name="ext-link">
				<xsl:attribute name="ext-link-type">
					<xsl:text>uri</xsl:text>
				</xsl:attribute>
				<xsl:attribute name="xlink:type">
					<xsl:text>simple</xsl:text>
				</xsl:attribute>
				<xsl:attribute name="xlink:href">
					<xsl:value-of select="."/>
				</xsl:attribute>
				<xsl:value-of select="."/>
			</xsl:element>
		</xsl:element>
	</xsl:template>
	<xsl:template match="date-in-citation[@content-type='access-date']">
		<xsl:element name="access-date">
			<xsl:text>accessed </xsl:text>
			<xsl:value-of select="year"/>
			<xsl:if test="month!=''">
				<xsl:text> </xsl:text>
				<xsl:value-of select="month"/>
				<xsl:if test="day!=''">
					<xsl:text> </xsl:text>
					<xsl:value-of select="day"/>
				</xsl:if>
			</xsl:if>
		</xsl:element>
	</xsl:template>
	<xsl:template match="chapter-title">
		<xsl:element name="article-title">
			<xsl:value-of select="."/>
		</xsl:element>
	</xsl:template>

	<!-- Elements to be discarded -->
	<xsl:template match="size"/>
</xsl:stylesheet>
