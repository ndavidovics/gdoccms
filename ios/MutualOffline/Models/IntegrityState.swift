import Foundation

struct IntegrityState: Equatable {
    var vpnActive: Bool
    var dndActive: Bool?
    var notificationSuppressionActive: Bool
    var networkBlockActive: Bool

    static let inactive = IntegrityState(
        vpnActive: false,
        dndActive: nil,
        notificationSuppressionActive: false,
        networkBlockActive: false
    )
}
