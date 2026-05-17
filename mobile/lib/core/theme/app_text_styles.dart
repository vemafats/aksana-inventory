import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'app_colors.dart';

class AppTextStyles {
  AppTextStyles._();

  static final TextStyle screenTitle = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 24,
    fontWeight: FontWeight.w700,
    letterSpacing: -0.5,
    color: AppColors.voidBlack,
  );
  static final TextStyle backLabel = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 10,
    fontWeight: FontWeight.w600,
    letterSpacing: 1.5,
    color: AppColors.muted,
  );
  static final TextStyle cardTitle = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 14,
    fontWeight: FontWeight.w600,
    color: AppColors.voidBlack,
  );
  static final TextStyle cardSubtitle = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 11,
    fontWeight: FontWeight.w400,
    color: AppColors.muted,
  );
  static final TextStyle sectionLabel = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 10,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.5,
    color: AppColors.muted,
  );
  static final TextStyle tabLabel = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 9,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.0,
  );
  static final TextStyle buttonPrimary = TextStyle(
    fontFamily: GoogleFonts.inter().fontFamily,
    fontSize: 11,
    fontWeight: FontWeight.w700,
    letterSpacing: 1.5,
    color: Colors.white,
  );
  static final TextStyle mono = TextStyle(
    fontFamily: GoogleFonts.ibmPlexMono().fontFamily,
    fontSize: 13,
    fontWeight: FontWeight.w400,
    color: AppColors.voidBlack,
  );
  static final TextStyle monoBold = TextStyle(
    fontFamily: GoogleFonts.ibmPlexMono().fontFamily,
    fontSize: 13,
    fontWeight: FontWeight.w700,
    color: AppColors.voidBlack,
  );
  static final TextStyle monoLarge = TextStyle(
    fontFamily: GoogleFonts.ibmPlexMono().fontFamily,
    fontSize: 28,
    fontWeight: FontWeight.w700,
    color: Colors.white,
  );
  static final TextStyle monoMuted = TextStyle(
    fontFamily: GoogleFonts.ibmPlexMono().fontFamily,
    fontSize: 11,
    fontWeight: FontWeight.w400,
    color: AppColors.muted,
  );
}
