package app.mutualoffline.ui

import android.content.Intent
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.getValue
import androidx.compose.ui.platform.LocalContext
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import app.mutualoffline.MutualOfflineApplication
import app.mutualoffline.ui.auth.LoginScreen
import app.mutualoffline.ui.auth.RegisterScreen
import app.mutualoffline.ui.permissions.PermissionSetupScreen
import app.mutualoffline.ui.session.ActiveSessionScreen
import app.mutualoffline.ui.session.CreateSessionScreen
import app.mutualoffline.ui.session.EndRequestScreen
import app.mutualoffline.ui.session.HomeScreen
import app.mutualoffline.ui.session.ProfileStatsScreen
import app.mutualoffline.ui.session.QRDisplayScreen
import app.mutualoffline.ui.session.QRScannerScreen
import app.mutualoffline.ui.session.ReadyToLockScreen
import app.mutualoffline.ui.session.SessionResultScreen
import app.mutualoffline.ui.social.FeedScreen
import app.mutualoffline.ui.social.FindFriendsScreen
import app.mutualoffline.ui.social.FriendsListScreen
import app.mutualoffline.ui.social.PrivacySettingsScreen

object Routes {
    const val LOGIN = "login"
    const val REGISTER = "register"
    const val PERMISSIONS = "permissions"
    const val HOME = "home"
    const val CREATE_SESSION = "session/create"
    const val QR_DISPLAY = "session/qr/{uuid}"
    const val QR_SCAN = "session/scan"
    const val READY = "session/ready/{uuid}"
    const val ACTIVE = "session/active/{uuid}"
    const val END_REQUEST = "session/end/{uuid}"
    const val RESULT = "session/result/{uuid}"
    const val PROFILE = "profile/stats"
    const val FIND_FRIENDS = "social/find"
    const val FRIENDS_LIST = "social/friends"
    const val FEED = "social/feed"
    const val PRIVACY = "social/privacy"

    fun qrDisplay(uuid: String) = "session/qr/$uuid"
    fun ready(uuid: String) = "session/ready/$uuid"
    fun active(uuid: String) = "session/active/$uuid"
    fun endRequest(uuid: String) = "session/end/$uuid"
    fun result(uuid: String) = "session/result/$uuid"
}

@Composable
fun MutualOfflineApp(
    onRequestVpnPermission: (Intent?) -> Unit,
    prepareVpn: () -> Intent?,
) {
    val context = LocalContext.current
    val app = context.applicationContext as MutualOfflineApplication

    val navController = rememberNavController()
    var startDestination by remember {
        mutableStateOf(if (app.authRepository.isAuthenticated()) Routes.HOME else Routes.LOGIN)
    }

    LaunchedEffect(Unit) {
        // Ensure a stable device UUID exists from first launch.
        app.deviceRepository.ensureDeviceUuid()
    }

    NavHost(
        navController = navController,
        startDestination = startDestination,
    ) {
        composable(Routes.LOGIN) {
            LoginScreen(
                authRepository = app.authRepository,
                onLoggedIn = {
                    startDestination = Routes.HOME
                    navController.navigate(Routes.HOME) {
                        popUpTo(Routes.LOGIN) { inclusive = true }
                    }
                },
                onGoToRegister = { navController.navigate(Routes.REGISTER) },
            )
        }
        composable(Routes.REGISTER) {
            RegisterScreen(
                authRepository = app.authRepository,
                onRegistered = {
                    navController.navigate(Routes.PERMISSIONS) {
                        popUpTo(Routes.REGISTER) { inclusive = true }
                    }
                },
                onBackToLogin = { navController.popBackStack() },
            )
        }
        composable(Routes.PERMISSIONS) {
            PermissionSetupScreen(
                onRequestVpnPermission = { onRequestVpnPermission(prepareVpn()) },
                onRequestDndPermission = { app.dndService.requestPolicyAccess() },
                onContinue = {
                    navController.navigate(Routes.HOME) {
                        popUpTo(Routes.PERMISSIONS) { inclusive = true }
                    }
                },
            )
        }
        composable(Routes.HOME) {
            HomeScreen(
                onCreate = { navController.navigate(Routes.CREATE_SESSION) },
                onScan = { navController.navigate(Routes.QR_SCAN) },
                onProfile = { navController.navigate(Routes.PROFILE) },
                onFeed = { navController.navigate(Routes.FEED) },
                onFriends = { navController.navigate(Routes.FRIENDS_LIST) },
                onFindFriends = { navController.navigate(Routes.FIND_FRIENDS) },
                onPrivacy = { navController.navigate(Routes.PRIVACY) },
                onPermissions = { navController.navigate(Routes.PERMISSIONS) },
                onLogout = {
                    navController.navigate(Routes.LOGIN) {
                        popUpTo(0) { inclusive = true }
                    }
                },
                authRepository = app.authRepository,
            )
        }
        composable(Routes.CREATE_SESSION) {
            CreateSessionScreen(
                sessionRepository = app.sessionRepository,
                onSessionCreated = { uuid -> navController.navigate(Routes.qrDisplay(uuid)) },
                onBack = { navController.popBackStack() },
            )
        }
        composable(Routes.QR_DISPLAY) { entry ->
            val uuid = entry.arguments?.getString("uuid").orEmpty()
            QRDisplayScreen(
                sessionUuid = uuid,
                sessionRepository = app.sessionRepository,
                onPartnerJoined = { navController.navigate(Routes.ready(uuid)) },
                onCancel = { navController.popBackStack() },
            )
        }
        composable(Routes.QR_SCAN) {
            QRScannerScreen(
                sessionRepository = app.sessionRepository,
                onJoined = { uuid -> navController.navigate(Routes.ready(uuid)) },
                onBack = { navController.popBackStack() },
            )
        }
        composable(Routes.READY) { entry ->
            val uuid = entry.arguments?.getString("uuid").orEmpty()
            ReadyToLockScreen(
                sessionUuid = uuid,
                sessionRepository = app.sessionRepository,
                protectionService = app.protectionService,
                onActive = { navController.navigate(Routes.active(uuid)) },
                onCancel = { navController.popBackStack() },
            )
        }
        composable(Routes.ACTIVE) { entry ->
            val uuid = entry.arguments?.getString("uuid").orEmpty()
            ActiveSessionScreen(
                sessionUuid = uuid,
                sessionRepository = app.sessionRepository,
                onRequestEnd = { navController.navigate(Routes.endRequest(uuid)) },
                onResult = { navController.navigate(Routes.result(uuid)) },
            )
        }
        composable(Routes.END_REQUEST) { entry ->
            val uuid = entry.arguments?.getString("uuid").orEmpty()
            EndRequestScreen(
                sessionUuid = uuid,
                sessionRepository = app.sessionRepository,
                onResult = { navController.navigate(Routes.result(uuid)) },
            )
        }
        composable(Routes.RESULT) { entry ->
            val uuid = entry.arguments?.getString("uuid").orEmpty()
            SessionResultScreen(
                sessionUuid = uuid,
                sessionRepository = app.sessionRepository,
                protectionService = app.protectionService,
                onHome = {
                    navController.navigate(Routes.HOME) {
                        popUpTo(0) { inclusive = true }
                    }
                },
            )
        }
        composable(Routes.PROFILE) {
            ProfileStatsScreen(
                socialRepository = app.socialRepository,
                onBack = { navController.popBackStack() },
            )
        }
        composable(Routes.FIND_FRIENDS) {
            FindFriendsScreen(
                socialRepository = app.socialRepository,
                onBack = { navController.popBackStack() },
            )
        }
        composable(Routes.FRIENDS_LIST) {
            FriendsListScreen(
                socialRepository = app.socialRepository,
                onBack = { navController.popBackStack() },
            )
        }
        composable(Routes.FEED) {
            FeedScreen(
                socialRepository = app.socialRepository,
                onBack = { navController.popBackStack() },
            )
        }
        composable(Routes.PRIVACY) {
            PrivacySettingsScreen(
                socialRepository = app.socialRepository,
                onBack = { navController.popBackStack() },
            )
        }
    }
}
