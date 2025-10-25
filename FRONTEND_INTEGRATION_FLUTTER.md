# Flutter Frontend Integration Guide

## Installment Manager API - Flutter Integration

This guide provides comprehensive examples for integrating the Installment Manager API with Flutter applications.

## Table of Contents

-   [Setup](#setup)
-   [Dependencies](#dependencies)
-   [API Service](#api-service)
-   [Models](#models)
-   [Authentication](#authentication)
-   [Dashboard Screens](#dashboard-screens)
-   [Customer Management](#customer-management)
-   [Installment Management](#installment-management)
-   [Error Handling](#error-handling)
-   [Complete Examples](#complete-examples)

## Setup

### 1. Create Flutter Project

```bash
flutter create installment_manager_app
cd installment_manager_app
```

## Dependencies

### `pubspec.yaml`

```yaml
dependencies:
    flutter:
        sdk: flutter

    # HTTP & API
    http: ^1.1.0
    dio: ^5.3.2

    # State Management
    provider: ^6.0.5
    riverpod: ^2.4.9
    flutter_riverpod: ^2.4.9

    # UI Components
    cupertino_icons: ^1.0.2
    flutter_screenutil: ^5.9.0

    # Data & Storage
    shared_preferences: ^2.2.2
    json_annotation: ^4.8.1

    # Charts & Visualization
    fl_chart: ^0.65.0

    # Date & Time
    intl: ^0.18.1

    # Utils
    logger: ^2.0.2+1

dev_dependencies:
    flutter_test:
        sdk: flutter
    flutter_lints: ^3.0.0
    json_serializable: ^6.7.1
    build_runner: ^2.4.7
```

## API Service

### `lib/services/api_service.dart`

```dart
import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:logger/logger.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  late Dio _dio;
  final Logger _logger = Logger();
  String? _baseUrl = 'https://installment-back.ammar-system.online/api';

  void init() {
    _dio = Dio(BaseOptions(
      baseUrl: _baseUrl!,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));

    // Add interceptors
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        _logger.d('Request: ${options.method} ${options.path}');
        handler.next(options);
      },
      onResponse: (response, handler) {
        _logger.d('Response: ${response.statusCode} ${response.requestOptions.path}');
        handler.next(response);
      },
      onError: (error, handler) {
        _logger.e('Error: ${error.message}');
        if (error.response?.statusCode == 401) {
          _clearToken();
        }
        handler.next(error);
      },
    ));
  }

  Future<String?> _getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('auth_token');
  }

  Future<void> _setToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> _clearToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }

  // Auth endpoints
  Future<ApiResponse<AuthData>> login(Map<String, dynamic> credentials) async {
    try {
      final response = await _dio.post('/auth/login', data: credentials);
      final authData = AuthData.fromJson(response.data['data']);
      await _setToken(authData.token);
      return ApiResponse.success(authData, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<AuthData>> register(Map<String, dynamic> userData) async {
    try {
      final response = await _dio.post('/auth/register', data: userData);
      final authData = AuthData.fromJson(response.data['data']);
      await _setToken(authData.token);
      return ApiResponse.success(authData, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<void>> logout() async {
    try {
      await _dio.post('/auth/logout');
      await _clearToken();
      return ApiResponse.success(null, 'Logged out successfully');
    } on DioException catch (e) {
      await _clearToken();
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<User>> getMe() async {
    try {
      final response = await _dio.get('/auth/me');
      final user = User.fromJson(response.data['data']);
      return ApiResponse.success(user, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  // Dashboard endpoints
  Future<ApiResponse<DashboardData>> getDashboardAnalytics() async {
    try {
      final response = await _dio.get('/dashboard');
      final dashboardData = DashboardData.fromJson(response.data['data']);
      return ApiResponse.success(dashboardData, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  // Customer endpoints
  Future<ApiResponse<PaginatedResponse<Customer>>> getCustomers({
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final response = await _dio.get('/customer-list', queryParameters: {
        'page': page,
        'per_page': perPage,
      });
      final customers = PaginatedResponse<Customer>.fromJson(
        response.data['data'],
        (json) => Customer.fromJson(json),
      );
      return ApiResponse.success(customers, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<Customer>> createCustomer(Map<String, dynamic> data) async {
    try {
      final response = await _dio.post('/customer-create', data: data);
      final customer = Customer.fromJson(response.data['data']);
      return ApiResponse.success(customer, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<Customer>> updateCustomer(int id, Map<String, dynamic> data) async {
    try {
      final response = await _dio.put('/customer-update/$id', data: data);
      final customer = Customer.fromJson(response.data['data']);
      return ApiResponse.success(customer, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<void>> deleteCustomer(int id) async {
    try {
      await _dio.delete('/customer-delete/$id');
      return ApiResponse.success(null, 'Customer deleted successfully');
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  // Installment endpoints
  Future<ApiResponse<PaginatedResponse<Installment>>> getInstallments({
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final response = await _dio.get('/installment-list', queryParameters: {
        'page': page,
        'per_page': perPage,
      });
      final installments = PaginatedResponse<Installment>.fromJson(
        response.data['data'],
        (json) => Installment.fromJson(json),
      );
      return ApiResponse.success(installments, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<Installment>> createInstallment(Map<String, dynamic> data) async {
    try {
      final response = await _dio.post('/installment-create', data: data);
      final installment = Installment.fromJson(response.data['data']);
      return ApiResponse.success(installment, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  Future<ApiResponse<InstallmentItem>> markItemPaid(int itemId, Map<String, dynamic> data) async {
    try {
      final response = await _dio.post('/installment-item-pay/$itemId', data: data);
      final item = InstallmentItem.fromJson(response.data['data']);
      return ApiResponse.success(item, response.data['message']);
    } on DioException catch (e) {
      return ApiResponse.error(_handleError(e));
    }
  }

  String _handleError(DioException error) {
    if (error.response?.data != null) {
      final data = error.response!.data;
      return data['message'] ?? 'An error occurred';
    }
    return error.message ?? 'Network error';
  }
}

class ApiResponse<T> {
  final bool success;
  final T? data;
  final String message;
  final String? errorCode;

  ApiResponse._({
    required this.success,
    this.data,
    required this.message,
    this.errorCode,
  });

  factory ApiResponse.success(T data, String message) {
    return ApiResponse._(
      success: true,
      data: data,
      message: message,
    );
  }

  factory ApiResponse.error(String message, [String? errorCode]) {
    return ApiResponse._(
      success: false,
      message: message,
      errorCode: errorCode,
    );
  }
}
```

## Models

### `lib/models/user.dart`

```dart
import 'package:json_annotation/json_annotation.dart';

part 'user.g.dart';

@JsonSerializable()
class User {
  final int id;
  final String name;
  final String email;
  final String role;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;

  User({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.createdAt,
  });

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
  Map<String, dynamic> toJson() => _$UserToJson(this);

  bool get isOwner => role == 'owner';
}

@JsonSerializable()
class AuthData {
  final User user;
  final String token;
  @JsonKey(name: 'token_type')
  final String tokenType;

  AuthData({
    required this.user,
    required this.token,
    required this.tokenType,
  });

  factory AuthData.fromJson(Map<String, dynamic> json) => _$AuthDataFromJson(json);
  Map<String, dynamic> toJson() => _$AuthDataToJson(this);
}
```

### `lib/models/customer.dart`

```dart
import 'package:json_annotation/json_annotation.dart';

part 'customer.g.dart';

@JsonSerializable()
class Customer {
  final int id;
  final String name;
  final String email;
  final String phone;
  final String? address;
  final String? notes;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;

  Customer({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    this.address,
    this.notes,
    required this.createdAt,
  });

  factory Customer.fromJson(Map<String, dynamic> json) => _$CustomerFromJson(json);
  Map<String, dynamic> toJson() => _$CustomerToJson(this);
}

@JsonSerializable()
class CustomerStats {
  @JsonKey(name: 'total_installments')
  final int totalInstallments;
  @JsonKey(name: 'active_installments')
  final int activeInstallments;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  @JsonKey(name: 'paid_amount')
  final double paidAmount;

  CustomerStats({
    required this.totalInstallments,
    required this.activeInstallments,
    required this.totalAmount,
    required this.paidAmount,
  });

  factory CustomerStats.fromJson(Map<String, dynamic> json) => _$CustomerStatsFromJson(json);
  Map<String, dynamic> toJson() => _$CustomerStatsToJson(this);
}
```

### `lib/models/installment.dart`

```dart
import 'package:json_annotation/json_annotation.dart';

part 'installment.g.dart';

@JsonSerializable()
class Installment {
  final int id;
  @JsonKey(name: 'customer_id')
  final int customerId;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  final List<dynamic> products;
  @JsonKey(name: 'start_date')
  final DateTime startDate;
  final int months;
  @JsonKey(name: 'end_date')
  final DateTime? endDate;
  final String status;
  final String? notes;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  final Customer? customer;
  final List<InstallmentItem>? items;

  Installment({
    required this.id,
    required this.customerId,
    required this.totalAmount,
    required this.products,
    required this.startDate,
    required this.months,
    this.endDate,
    required this.status,
    this.notes,
    required this.createdAt,
    this.customer,
    this.items,
  });

  factory Installment.fromJson(Map<String, dynamic> json) => _$InstallmentFromJson(json);
  Map<String, dynamic> toJson() => _$InstallmentToJson(this);
}

@JsonSerializable()
class InstallmentItem {
  final int id;
  @JsonKey(name: 'installment_id')
  final int installmentId;
  @JsonKey(name: 'due_date')
  final DateTime dueDate;
  final double amount;
  final String status;
  @JsonKey(name: 'paid_amount')
  final double? paidAmount;
  @JsonKey(name: 'paid_at')
  final DateTime? paidAt;
  final String? reference;

  InstallmentItem({
    required this.id,
    required this.installmentId,
    required this.dueDate,
    required this.amount,
    required this.status,
    this.paidAmount,
    this.paidAt,
    this.reference,
  });

  factory InstallmentItem.fromJson(Map<String, dynamic> json) => _$InstallmentItemFromJson(json);
  Map<String, dynamic> toJson() => _$InstallmentItemToJson(this);
}
```

### `lib/models/dashboard.dart`

```dart
import 'package:json_annotation/json_annotation.dart';
import 'customer.dart';
import 'installment.dart';

part 'dashboard.g.dart';

@JsonSerializable()
class DashboardData {
  @JsonKey(name: 'dueSoon')
  final int dueSoon;
  final int overdue;
  final double outstanding;
  @JsonKey(name: 'collectedThisMonth')
  final double collectedThisMonth;
  @JsonKey(name: 'totalInstallments')
  final int totalInstallments;
  @JsonKey(name: 'activeInstallments')
  final int activeInstallments;
  @JsonKey(name: 'completedInstallments')
  final int completedInstallments;
  @JsonKey(name: 'totalCustomers')
  final int totalCustomers;
  @JsonKey(name: 'activeCustomers')
  final int activeCustomers;
  @JsonKey(name: 'collectedLastMonth')
  final double collectedLastMonth;
  @JsonKey(name: 'collectionGrowth')
  final double collectionGrowth;
  final List<UpcomingPayment> upcoming;
  @JsonKey(name: 'overduePayments')
  final List<OverduePayment> overduePayments;
  @JsonKey(name: 'recentPayments')
  final List<RecentPayment> recentPayments;
  @JsonKey(name: 'topCustomers')
  final List<TopCustomer> topCustomers;
  @JsonKey(name: 'monthlyTrend')
  final List<MonthlyTrend> monthlyTrend;

  DashboardData({
    required this.dueSoon,
    required this.overdue,
    required this.outstanding,
    required this.collectedThisMonth,
    required this.totalInstallments,
    required this.activeInstallments,
    required this.completedInstallments,
    required this.totalCustomers,
    required this.activeCustomers,
    required this.collectedLastMonth,
    required this.collectionGrowth,
    required this.upcoming,
    required this.overduePayments,
    required this.recentPayments,
    required this.topCustomers,
    required this.monthlyTrend,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) => _$DashboardDataFromJson(json);
  Map<String, dynamic> toJson() => _$DashboardDataToJson(this);
}

@JsonSerializable()
class UpcomingPayment {
  @JsonKey(name: 'installment_id')
  final int installmentId;
  @JsonKey(name: 'customer_name')
  final String customerName;
  @JsonKey(name: 'customer_email')
  final String customerEmail;
  @JsonKey(name: 'customer_phone')
  final String customerPhone;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  final int months;
  @JsonKey(name: 'due_date')
  final DateTime dueDate;
  final double amount;
  final String status;
  @JsonKey(name: 'item_status')
  final String itemStatus;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'days_until_due')
  final int daysUntilDue;

  UpcomingPayment({
    required this.installmentId,
    required this.customerName,
    required this.customerEmail,
    required this.customerPhone,
    required this.totalAmount,
    required this.months,
    required this.dueDate,
    required this.amount,
    required this.status,
    required this.itemStatus,
    required this.createdAt,
    required this.daysUntilDue,
  });

  factory UpcomingPayment.fromJson(Map<String, dynamic> json) => _$UpcomingPaymentFromJson(json);
  Map<String, dynamic> toJson() => _$UpcomingPaymentToJson(this);
}

@JsonSerializable()
class OverduePayment {
  @JsonKey(name: 'installment_id')
  final int installmentId;
  @JsonKey(name: 'customer_name')
  final String customerName;
  @JsonKey(name: 'customer_email')
  final String customerEmail;
  @JsonKey(name: 'customer_phone')
  final String customerPhone;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  final int months;
  @JsonKey(name: 'due_date')
  final DateTime dueDate;
  final double amount;
  final String status;
  @JsonKey(name: 'item_status')
  final String itemStatus;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'days_overdue')
  final int daysOverdue;

  OverduePayment({
    required this.installmentId,
    required this.customerName,
    required this.customerEmail,
    required this.customerPhone,
    required this.totalAmount,
    required this.months,
    required this.dueDate,
    required this.amount,
    required this.status,
    required this.itemStatus,
    required this.createdAt,
    required this.daysOverdue,
  });

  factory OverduePayment.fromJson(Map<String, dynamic> json) => _$OverduePaymentFromJson(json);
  Map<String, dynamic> toJson() => _$OverduePaymentToJson(this);
}

@JsonSerializable()
class RecentPayment {
  @JsonKey(name: 'installment_id')
  final int installmentId;
  @JsonKey(name: 'customer_name')
  final String customerName;
  @JsonKey(name: 'customer_email')
  final String customerEmail;
  @JsonKey(name: 'due_date')
  final DateTime dueDate;
  final double amount;
  @JsonKey(name: 'paid_amount')
  final double paidAmount;
  @JsonKey(name: 'paid_at')
  final DateTime paidAt;
  final String? reference;
  @JsonKey(name: 'days_since_paid')
  final int daysSincePaid;

  RecentPayment({
    required this.installmentId,
    required this.customerName,
    required this.customerEmail,
    required this.dueDate,
    required this.amount,
    required this.paidAmount,
    required this.paidAt,
    this.reference,
    required this.daysSincePaid,
  });

  factory RecentPayment.fromJson(Map<String, dynamic> json) => _$RecentPaymentFromJson(json);
  Map<String, dynamic> toJson() => _$RecentPaymentToJson(this);
}

@JsonSerializable()
class TopCustomer {
  final int id;
  final String name;
  final String email;
  final String phone;
  @JsonKey(name: 'active_installments')
  final int activeInstallments;
  @JsonKey(name: 'total_outstanding')
  final double totalOutstanding;

  TopCustomer({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.activeInstallments,
    required this.totalOutstanding,
  });

  factory TopCustomer.fromJson(Map<String, dynamic> json) => _$TopCustomerFromJson(json);
  Map<String, dynamic> toJson() => _$TopCustomerToJson(this);
}

@JsonSerializable()
class MonthlyTrend {
  final String month;
  final double amount;
  final int year;
  @JsonKey(name: 'month_number')
  final int monthNumber;

  MonthlyTrend({
    required this.month,
    required this.amount,
    required this.year,
    required this.monthNumber,
  });

  factory MonthlyTrend.fromJson(Map<String, dynamic> json) => _$MonthlyTrendFromJson(json);
  Map<String, dynamic> toJson() => _$MonthlyTrendToJson(this);
}

@JsonSerializable()
class PaginatedResponse<T> {
  final List<T> data;
  @JsonKey(name: 'current_page')
  final int currentPage;
  @JsonKey(name: 'per_page')
  final int perPage;
  final int total;

  PaginatedResponse({
    required this.data,
    required this.currentPage,
    required this.perPage,
    required this.total,
  });

  factory PaginatedResponse.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJsonT,
  ) {
    return PaginatedResponse<T>(
      data: (json['data'] as List).map((item) => fromJsonT(item)).toList(),
      currentPage: json['current_page'],
      perPage: json['per_page'],
      total: json['total'],
    );
  }
}
```

## Authentication

### `lib/providers/auth_provider.dart`

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/user.dart';
import '../services/api_service.dart';

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier() : super(AuthState.initial()) {
    _checkAuthStatus();
  }

  final ApiService _apiService = ApiService();

  Future<void> _checkAuthStatus() async {
    state = state.copyWith(isLoading: true);

    try {
      final response = await _apiService.getMe();
      if (response.success && response.data != null) {
        state = state.copyWith(
          isLoading: false,
          isAuthenticated: true,
          user: response.data!,
        );
      } else {
        state = state.copyWith(isLoading: false);
      }
    } catch (e) {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true);

    try {
      final response = await _apiService.login({
        'email': email,
        'password': password,
      });

      if (response.success && response.data != null) {
        state = state.copyWith(
          isLoading: false,
          isAuthenticated: true,
          user: response.data!.user,
        );
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.message,
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
      return false;
    }
  }

  Future<bool> register(String name, String email, String password) async {
    state = state.copyWith(isLoading: true);

    try {
      final response = await _apiService.register({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      });

      if (response.success && response.data != null) {
        state = state.copyWith(
          isLoading: false,
          isAuthenticated: true,
          user: response.data!.user,
        );
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.message,
        );
        return false;
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
      return false;
    }
  }

  Future<void> logout() async {
    await _apiService.logout();
    state = AuthState.initial();
  }
}

class AuthState {
  final bool isLoading;
  final bool isAuthenticated;
  final User? user;
  final String? error;

  AuthState({
    required this.isLoading,
    required this.isAuthenticated,
    this.user,
    this.error,
  });

  factory AuthState.initial() => AuthState(
    isLoading: false,
    isAuthenticated: false,
  );

  AuthState copyWith({
    bool? isLoading,
    bool? isAuthenticated,
    User? user,
    String? error,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      user: user ?? this.user,
      error: error ?? this.error,
    );
  }

  bool get isOwner => user?.isOwner ?? false;
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier();
});
```

## Dashboard Screens

### `lib/screens/dashboard_screen.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../providers/dashboard_provider.dart';
import '../widgets/dashboard_stats_card.dart';
import '../widgets/upcoming_payments_table.dart';
import '../widgets/overdue_payments_table.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardState = ref.watch(dashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
      ),
      body: dashboardState.when(
        data: (dashboardData) => _buildDashboard(context, dashboardData),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error, size: 64, color: Colors.red[300]),
              const SizedBox(height: 16),
              Text('Error: $error'),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.refresh(dashboardProvider),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDashboard(BuildContext context, DashboardData data) {
    return SingleChildScrollView(
      padding: EdgeInsets.all(16.w),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Stats Cards
          _buildStatsSection(data),
          SizedBox(height: 24.h),

          // Tables Section
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: UpcomingPaymentsTable(
                  payments: data.upcoming,
                  onMarkPaid: (payment) => _handleMarkPaid(context, payment),
                ),
              ),
              SizedBox(width: 16.w),
              Expanded(
                child: OverduePaymentsTable(
                  payments: data.overduePayments,
                  onMarkPaid: (payment) => _handleMarkPaid(context, payment),
                  onContact: (payment) => _handleContact(context, payment),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatsSection(DashboardData data) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Overview',
          style: TextStyle(
            fontSize: 24.sp,
            fontWeight: FontWeight.bold,
          ),
        ),
        SizedBox(height: 16.h),
        Row(
          children: [
            Expanded(
              child: DashboardStatsCard(
                title: 'Due Soon',
                value: data.dueSoon.toString(),
                icon: Icons.schedule,
                color: Colors.blue,
              ),
            ),
            SizedBox(width: 16.w),
            Expanded(
              child: DashboardStatsCard(
                title: 'Overdue',
                value: data.overdue.toString(),
                icon: Icons.warning,
                color: Colors.red,
              ),
            ),
          ],
        ),
        SizedBox(height: 16.h),
        Row(
          children: [
            Expanded(
              child: DashboardStatsCard(
                title: 'Outstanding',
                value: '\$${data.outstanding.toStringAsFixed(2)}',
                icon: Icons.account_balance_wallet,
                color: Colors.green,
              ),
            ),
            SizedBox(width: 16.w),
            Expanded(
              child: DashboardStatsCard(
                title: 'Collected This Month',
                value: '\$${data.collectedThisMonth.toStringAsFixed(2)}',
                icon: Icons.trending_up,
                color: Colors.purple,
              ),
            ),
          ],
        ),
      ],
    );
  }

  void _handleMarkPaid(BuildContext context, dynamic payment) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Mark as Paid'),
        content: const Text('Are you sure you want to mark this payment as paid?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              // Implement mark as paid logic
              Navigator.pop(context);
            },
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
  }

  void _handleContact(BuildContext context, dynamic payment) {
    // Implement contact customer logic
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Contacting ${payment.customerName}...')),
    );
  }
}
```

### `lib/widgets/dashboard_stats_card.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

class DashboardStatsCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;

  const DashboardStatsCard({
    Key? key,
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 4,
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 24.sp),
                SizedBox(width: 8.w),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      fontSize: 14.sp,
                      color: Colors.grey[600],
                    ),
                  ),
                ),
              ],
            ),
            SizedBox(height: 8.h),
            Text(
              value,
              style: TextStyle(
                fontSize: 24.sp,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

### `lib/widgets/upcoming_payments_table.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../models/dashboard.dart';

class UpcomingPaymentsTable extends StatelessWidget {
  final List<UpcomingPayment> payments;
  final Function(UpcomingPayment) onMarkPaid;

  const UpcomingPaymentsTable({
    Key? key,
    required this.payments,
    required this.onMarkPaid,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: EdgeInsets.all(16.w),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Upcoming Payments',
              style: TextStyle(
                fontSize: 18.sp,
                fontWeight: FontWeight.bold,
              ),
            ),
            SizedBox(height: 16.h),
            if (payments.isEmpty)
              const Center(
                child: Text('No upcoming payments'),
              )
            else
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: DataTable(
                  columns: [
                    DataColumn(label: Text('Customer', style: TextStyle(fontSize: 12.sp))),
                    DataColumn(label: Text('Due Date', style: TextStyle(fontSize: 12.sp))),
                    DataColumn(label: Text('Amount', style: TextStyle(fontSize: 12.sp))),
                    DataColumn(label: Text('Days', style: TextStyle(fontSize: 12.sp))),
                    DataColumn(label: Text('Action', style: TextStyle(fontSize: 12.sp))),
                  ],
                  rows: payments.map((payment) {
                    return DataRow(
                      cells: [
                        DataCell(
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                payment.customerName,
                                style: TextStyle(fontSize: 11.sp, fontWeight: FontWeight.bold),
                              ),
                              Text(
                                payment.customerEmail,
                                style: TextStyle(fontSize: 10.sp, color: Colors.grey[600]),
                              ),
                            ],
                          ),
                        ),
                        DataCell(
                          Text(
                            _formatDate(payment.dueDate),
                            style: TextStyle(fontSize: 11.sp),
                          ),
                        ),
                        DataCell(
                          Text(
                            '\$${payment.amount.toStringAsFixed(2)}',
                            style: TextStyle(fontSize: 11.sp),
                          ),
                        ),
                        DataCell(
                          Container(
                            padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
                            decoration: BoxDecoration(
                              color: payment.daysUntilDue <= 1
                                  ? Colors.red[100]
                                  : payment.daysUntilDue <= 3
                                      ? Colors.orange[100]
                                      : Colors.green[100],
                              borderRadius: BorderRadius.circular(12.r),
                            ),
                            child: Text(
                              '${payment.daysUntilDue}d',
                              style: TextStyle(
                                fontSize: 10.sp,
                                color: payment.daysUntilDue <= 1
                                    ? Colors.red[700]
                                    : payment.daysUntilDue <= 3
                                        ? Colors.orange[700]
                                        : Colors.green[700],
                              ),
                            ),
                          ),
                        ),
                        DataCell(
                          ElevatedButton(
                            onPressed: () => onMarkPaid(payment),
                            style: ElevatedButton.styleFrom(
                              padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
                              minimumSize: Size.zero,
                            ),
                            child: Text(
                              'Mark Paid',
                              style: TextStyle(fontSize: 10.sp),
                            ),
                          ),
                        ),
                      ],
                    );
                  }).toList(),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }
}
```

## Customer Management

### `lib/screens/customers_screen.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../providers/customers_provider.dart';
import '../widgets/customer_form_dialog.dart';

class CustomersScreen extends ConsumerWidget {
  const CustomersScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final customersState = ref.watch(customersProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Customers'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            onPressed: () => _showCustomerForm(context, ref),
            icon: const Icon(Icons.add),
          ),
        ],
      ),
      body: customersState.when(
        data: (customers) => _buildCustomersList(context, ref, customers),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error, size: 64, color: Colors.red[300]),
              const SizedBox(height: 16),
              Text('Error: $error'),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.refresh(customersProvider),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCustomersList(BuildContext context, WidgetRef ref, List<Customer> customers) {
    return ListView.builder(
      padding: EdgeInsets.all(16.w),
      itemCount: customers.length,
      itemBuilder: (context, index) {
        final customer = customers[index];
        return Card(
          margin: EdgeInsets.only(bottom: 12.h),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: Colors.blue[100],
              child: Text(
                customer.name[0].toUpperCase(),
                style: TextStyle(
                  color: Colors.blue[700],
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            title: Text(
              customer.name,
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(customer.email),
                if (customer.phone.isNotEmpty) Text(customer.phone),
              ],
            ),
            trailing: PopupMenuButton(
              itemBuilder: (context) => [
                PopupMenuItem(
                  value: 'edit',
                  child: Row(
                    children: [
                      Icon(Icons.edit, size: 16.sp),
                      SizedBox(width: 8.w),
                      Text('Edit'),
                    ],
                  ),
                ),
                PopupMenuItem(
                  value: 'delete',
                  child: Row(
                    children: [
                      Icon(Icons.delete, size: 16.sp, color: Colors.red),
                      SizedBox(width: 8.w),
                      Text('Delete', style: TextStyle(color: Colors.red)),
                    ],
                  ),
                ),
              ],
              onSelected: (value) {
                switch (value) {
                  case 'edit':
                    _showCustomerForm(context, ref, customer);
                    break;
                  case 'delete':
                    _showDeleteDialog(context, ref, customer);
                    break;
                }
              },
            ),
          ),
        );
      },
    );
  }

  void _showCustomerForm(BuildContext context, WidgetRef ref, [Customer? customer]) {
    showDialog(
      context: context,
      builder: (context) => CustomerFormDialog(
        customer: customer,
        onSave: (customerData) {
          if (customer != null) {
            ref.read(customersProvider.notifier).updateCustomer(customer.id, customerData);
          } else {
            ref.read(customersProvider.notifier).createCustomer(customerData);
          }
        },
      ),
    );
  }

  void _showDeleteDialog(BuildContext context, WidgetRef ref, Customer customer) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Customer'),
        content: Text('Are you sure you want to delete ${customer.name}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              ref.read(customersProvider.notifier).deleteCustomer(customer.id);
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
  }
}
```

## Main App

### `lib/main.dart`

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/customers_screen.dart';
import 'services/api_service.dart';

void main() {
  runApp(
    ProviderScope(
      child: MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ScreenUtilInit(
      designSize: const Size(375, 812),
      minTextAdapt: true,
      splitScreenMode: true,
      builder: (context, child) {
        return MaterialApp(
          title: 'Installment Manager',
          theme: ThemeData(
            primarySwatch: Colors.blue,
            useMaterial3: true,
          ),
          home: const AuthWrapper(),
          routes: {
            '/login': (context) => const LoginScreen(),
            '/dashboard': (context) => const DashboardScreen(),
            '/customers': (context) => const CustomersScreen(),
          },
        );
      },
    );
  }
}

class AuthWrapper extends ConsumerWidget {
  const AuthWrapper({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authProvider);

    if (authState.isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (authState.isAuthenticated) {
      return const DashboardScreen();
    }

    return const LoginScreen();
  }
}
```

## Generate Models

Run this command to generate the model files:

```bash
flutter packages pub run build_runner build
```

This comprehensive Flutter integration guide provides everything needed to build a full-featured mobile application with the Installment Manager API. The code includes proper state management, error handling, and follows Flutter best practices.
