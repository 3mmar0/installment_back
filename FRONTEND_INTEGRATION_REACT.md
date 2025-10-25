# React Frontend Integration Guide

## Installment Manager API - React Integration

This guide provides comprehensive examples for integrating the Installment Manager API with React applications.

## Table of Contents

-   [Setup](#setup)
-   [API Service](#api-service)
-   [Authentication](#authentication)
-   [Dashboard Components](#dashboard-components)
-   [Customer Management](#customer-management)
-   [Installment Management](#installment-management)
-   [Error Handling](#error-handling)
-   [Complete Examples](#complete-examples)

## Setup

### 1. Install Dependencies

```bash
npm install axios react-query @tanstack/react-query
# or
yarn add axios react-query @tanstack/react-query
```

### 2. Environment Configuration

Create `.env` file:

```env
REACT_APP_API_BASE_URL=https://installment-back.ammar-system.online/api
REACT_APP_APP_NAME=Installment Manager
```

## API Service

### `src/services/api.js`

```javascript
import axios from "axios";

const API_BASE_URL = process.env.REACT_APP_API_BASE_URL;

// Create axios instance
const api = axios.create({
    baseURL: API_BASE_URL,
    headers: {
        "Content-Type": "application/json",
    },
});

// Request interceptor to add auth token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("auth_token");
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem("auth_token");
            window.location.href = "/login";
        }
        return Promise.reject(error);
    }
);

// API endpoints
export const authAPI = {
    login: (credentials) => api.post("/auth/login", credentials),
    register: (userData) => api.post("/auth/register", userData),
    logout: () => api.post("/auth/logout"),
    me: () => api.get("/auth/me"),
    refresh: () => api.post("/auth/refresh"),
};

export const customerAPI = {
    list: (params) => api.get("/customer-list", { params }),
    create: (data) => api.post("/customer-create", data),
    show: (id) => api.get(`/customer-show/${id}`),
    update: (id, data) => api.put(`/customer-update/${id}`, data),
    delete: (id) => api.delete(`/customer-delete/${id}`),
    stats: (id) => api.get(`/customer-stats/${id}`),
};

export const installmentAPI = {
    list: (params) => api.get("/installment-list", { params }),
    create: (data) => api.post("/installment-create", data),
    show: (id) => api.get(`/installment-show/${id}`),
    overdue: () => api.get("/installment-overdue"),
    dueSoon: () => api.get("/installment-due-soon"),
    markPaid: (itemId, data) =>
        api.post(`/installment-item-pay/${itemId}`, data),
};

export const dashboardAPI = {
    analytics: () => api.get("/dashboard"),
};

export const userAPI = {
    list: () => api.get("/user-list"),
    create: (data) => api.post("/user-create", data),
    show: (id) => api.get(`/user-show/${id}`),
    update: (id, data) => api.put(`/user-update/${id}`, data),
    delete: (id) => api.delete(`/user-delete/${id}`),
};

export default api;
```

## Authentication

### `src/contexts/AuthContext.js`

```javascript
import React, { createContext, useContext, useState, useEffect } from "react";
import { authAPI } from "../services/api";

const AuthContext = createContext();

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error("useAuth must be used within an AuthProvider");
    }
    return context;
};

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const initAuth = async () => {
            const token = localStorage.getItem("auth_token");
            if (token) {
                try {
                    const response = await authAPI.me();
                    setUser(response.data.data);
                } catch (error) {
                    localStorage.removeItem("auth_token");
                }
            }
            setLoading(false);
        };

        initAuth();
    }, []);

    const login = async (credentials) => {
        try {
            const response = await authAPI.login(credentials);
            const { user, token } = response.data.data;

            localStorage.setItem("auth_token", token);
            setUser(user);

            return { success: true, data: response.data };
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || "Login failed",
            };
        }
    };

    const register = async (userData) => {
        try {
            const response = await authAPI.register(userData);
            const { user, token } = response.data.data;

            localStorage.setItem("auth_token", token);
            setUser(user);

            return { success: true, data: response.data };
        } catch (error) {
            return {
                success: false,
                error: error.response?.data?.message || "Registration failed",
            };
        }
    };

    const logout = async () => {
        try {
            await authAPI.logout();
        } catch (error) {
            console.error("Logout error:", error);
        } finally {
            localStorage.removeItem("auth_token");
            setUser(null);
        }
    };

    const value = {
        user,
        loading,
        login,
        register,
        logout,
        isAuthenticated: !!user,
        isOwner: user?.role === "owner",
    };

    return (
        <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
    );
};
```

## Dashboard Components

### `src/components/Dashboard/DashboardStats.js`

```javascript
import React from "react";
import { Card, Row, Col, Statistic, Typography } from "antd";
import {
    DollarOutlined,
    UserOutlined,
    CalendarOutlined,
    ExclamationCircleOutlined,
} from "@ant-design/icons";

const { Title } = Typography;

const DashboardStats = ({ data }) => {
    const stats = [
        {
            title: "Due Soon",
            value: data?.dueSoon || 0,
            icon: <CalendarOutlined style={{ color: "#1890ff" }} />,
            color: "#1890ff",
        },
        {
            title: "Overdue",
            value: data?.overdue || 0,
            icon: <ExclamationCircleOutlined style={{ color: "#ff4d4f" }} />,
            color: "#ff4d4f",
        },
        {
            title: "Outstanding",
            value: `$${data?.outstanding?.toLocaleString() || 0}`,
            icon: <DollarOutlined style={{ color: "#52c41a" }} />,
            color: "#52c41a",
        },
        {
            title: "Collected This Month",
            value: `$${data?.collectedThisMonth?.toLocaleString() || 0}`,
            icon: <DollarOutlined style={{ color: "#722ed1" }} />,
            color: "#722ed1",
        },
    ];

    return (
        <Row gutter={[16, 16]}>
            {stats.map((stat, index) => (
                <Col xs={24} sm={12} lg={6} key={index}>
                    <Card>
                        <Statistic
                            title={stat.title}
                            value={stat.value}
                            prefix={stat.icon}
                            valueStyle={{ color: stat.color }}
                        />
                    </Card>
                </Col>
            ))}
        </Row>
    );
};

export default DashboardStats;
```

### `src/components/Dashboard/UpcomingPaymentsTable.js`

```javascript
import React from "react";
import { Table, Tag, Button, Space, Typography } from "antd";
import { PhoneOutlined, MailOutlined } from "@ant-design/icons";

const { Text } = Typography;

const UpcomingPaymentsTable = ({ data, onMarkPaid }) => {
    const columns = [
        {
            title: "Customer",
            key: "customer",
            render: (_, record) => (
                <div>
                    <div style={{ fontWeight: "bold" }}>
                        {record.customer_name}
                    </div>
                    <div style={{ fontSize: "12px", color: "#666" }}>
                        <MailOutlined /> {record.customer_email}
                    </div>
                    <div style={{ fontSize: "12px", color: "#666" }}>
                        <PhoneOutlined /> {record.customer_phone}
                    </div>
                </div>
            ),
        },
        {
            title: "Due Date",
            dataIndex: "due_date",
            key: "due_date",
            render: (date) => new Date(date).toLocaleDateString(),
        },
        {
            title: "Amount",
            dataIndex: "amount",
            key: "amount",
            render: (amount) => `$${amount.toLocaleString()}`,
        },
        {
            title: "Days Until Due",
            dataIndex: "days_until_due",
            key: "days_until_due",
            render: (days) => (
                <Tag color={days <= 1 ? "red" : days <= 3 ? "orange" : "green"}>
                    {days} days
                </Tag>
            ),
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            render: (status) => (
                <Tag color={status === "active" ? "green" : "default"}>
                    {status}
                </Tag>
            ),
        },
        {
            title: "Actions",
            key: "actions",
            render: (_, record) => (
                <Space>
                    <Button
                        type="primary"
                        size="small"
                        onClick={() => onMarkPaid(record)}
                    >
                        Mark Paid
                    </Button>
                </Space>
            ),
        },
    ];

    return (
        <Table
            columns={columns}
            dataSource={data}
            rowKey="item_id"
            pagination={{ pageSize: 10 }}
            scroll={{ x: 800 }}
        />
    );
};

export default UpcomingPaymentsTable;
```

### `src/components/Dashboard/OverduePaymentsTable.js`

```javascript
import React from "react";
import { Table, Tag, Button, Space, Typography } from "antd";
import {
    PhoneOutlined,
    MailOutlined,
    ExclamationCircleOutlined,
} from "@ant-design/icons";

const { Text } = Typography;

const OverduePaymentsTable = ({ data, onMarkPaid, onContact }) => {
    const columns = [
        {
            title: "Customer",
            key: "customer",
            render: (_, record) => (
                <div>
                    <div style={{ fontWeight: "bold" }}>
                        {record.customer_name}
                    </div>
                    <div style={{ fontSize: "12px", color: "#666" }}>
                        <MailOutlined /> {record.customer_email}
                    </div>
                    <div style={{ fontSize: "12px", color: "#666" }}>
                        <PhoneOutlined /> {record.customer_phone}
                    </div>
                </div>
            ),
        },
        {
            title: "Due Date",
            dataIndex: "due_date",
            key: "due_date",
            render: (date) => new Date(date).toLocaleDateString(),
        },
        {
            title: "Amount",
            dataIndex: "amount",
            key: "amount",
            render: (amount) => `$${amount.toLocaleString()}`,
        },
        {
            title: "Days Overdue",
            dataIndex: "days_overdue",
            key: "days_overdue",
            render: (days) => (
                <Tag color="red" icon={<ExclamationCircleOutlined />}>
                    {days} days overdue
                </Tag>
            ),
        },
        {
            title: "Actions",
            key: "actions",
            render: (_, record) => (
                <Space>
                    <Button
                        type="primary"
                        size="small"
                        onClick={() => onMarkPaid(record)}
                    >
                        Mark Paid
                    </Button>
                    <Button size="small" onClick={() => onContact(record)}>
                        Contact
                    </Button>
                </Space>
            ),
        },
    ];

    return (
        <Table
            columns={columns}
            dataSource={data}
            rowKey="item_id"
            pagination={{ pageSize: 10 }}
            scroll={{ x: 800 }}
        />
    );
};

export default OverduePaymentsTable;
```

## Customer Management

### `src/components/Customers/CustomerList.js`

```javascript
import React, { useState, useEffect } from "react";
import {
    Table,
    Button,
    Space,
    Modal,
    Form,
    Input,
    message,
    Popconfirm,
    Tag,
} from "antd";
import {
    PlusOutlined,
    EditOutlined,
    DeleteOutlined,
    EyeOutlined,
} from "@ant-design/icons";
import { customerAPI } from "../../services/api";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";

const CustomerList = () => {
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState(null);
    const [form] = Form.useForm();
    const queryClient = useQueryClient();

    // Fetch customers
    const { data: customers, isLoading } = useQuery({
        queryKey: ["customers"],
        queryFn: () => customerAPI.list(),
    });

    // Create customer mutation
    const createMutation = useMutation({
        mutationFn: customerAPI.create,
        onSuccess: () => {
            message.success("Customer created successfully");
            queryClient.invalidateQueries(["customers"]);
            setIsModalVisible(false);
            form.resetFields();
        },
        onError: (error) => {
            message.error(
                error.response?.data?.message || "Failed to create customer"
            );
        },
    });

    // Update customer mutation
    const updateMutation = useMutation({
        mutationFn: ({ id, data }) => customerAPI.update(id, data),
        onSuccess: () => {
            message.success("Customer updated successfully");
            queryClient.invalidateQueries(["customers"]);
            setIsModalVisible(false);
            setEditingCustomer(null);
            form.resetFields();
        },
        onError: (error) => {
            message.error(
                error.response?.data?.message || "Failed to update customer"
            );
        },
    });

    // Delete customer mutation
    const deleteMutation = useMutation({
        mutationFn: customerAPI.delete,
        onSuccess: () => {
            message.success("Customer deleted successfully");
            queryClient.invalidateQueries(["customers"]);
        },
        onError: (error) => {
            message.error(
                error.response?.data?.message || "Failed to delete customer"
            );
        },
    });

    const handleSubmit = (values) => {
        if (editingCustomer) {
            updateMutation.mutate({ id: editingCustomer.id, data: values });
        } else {
            createMutation.mutate(values);
        }
    };

    const handleEdit = (customer) => {
        setEditingCustomer(customer);
        form.setFieldsValue(customer);
        setIsModalVisible(true);
    };

    const handleDelete = (id) => {
        deleteMutation.mutate(id);
    };

    const columns = [
        {
            title: "Name",
            dataIndex: "name",
            key: "name",
        },
        {
            title: "Email",
            dataIndex: "email",
            key: "email",
        },
        {
            title: "Phone",
            dataIndex: "phone",
            key: "phone",
        },
        {
            title: "Address",
            dataIndex: "address",
            key: "address",
            render: (address) => address || "-",
        },
        {
            title: "Created",
            dataIndex: "created_at",
            key: "created_at",
            render: (date) => new Date(date).toLocaleDateString(),
        },
        {
            title: "Actions",
            key: "actions",
            render: (_, record) => (
                <Space>
                    <Button
                        icon={<EyeOutlined />}
                        size="small"
                        onClick={() => {
                            /* View customer details */
                        }}
                    >
                        View
                    </Button>
                    <Button
                        icon={<EditOutlined />}
                        size="small"
                        onClick={() => handleEdit(record)}
                    >
                        Edit
                    </Button>
                    <Popconfirm
                        title="Are you sure you want to delete this customer?"
                        onConfirm={() => handleDelete(record.id)}
                        okText="Yes"
                        cancelText="No"
                    >
                        <Button icon={<DeleteOutlined />} size="small" danger>
                            Delete
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <div>
            <div
                style={{
                    marginBottom: 16,
                    display: "flex",
                    justifyContent: "space-between",
                }}
            >
                <h2>Customers</h2>
                <Button
                    type="primary"
                    icon={<PlusOutlined />}
                    onClick={() => {
                        setEditingCustomer(null);
                        form.resetFields();
                        setIsModalVisible(true);
                    }}
                >
                    Add Customer
                </Button>
            </div>

            <Table
                columns={columns}
                dataSource={customers?.data?.data}
                loading={isLoading}
                rowKey="id"
                pagination={{
                    total: customers?.data?.total,
                    pageSize: customers?.data?.per_page,
                    showSizeChanger: true,
                    showQuickJumper: true,
                }}
            />

            <Modal
                title={editingCustomer ? "Edit Customer" : "Add Customer"}
                open={isModalVisible}
                onCancel={() => {
                    setIsModalVisible(false);
                    setEditingCustomer(null);
                    form.resetFields();
                }}
                footer={null}
            >
                <Form form={form} layout="vertical" onFinish={handleSubmit}>
                    <Form.Item
                        name="name"
                        label="Name"
                        rules={[
                            {
                                required: true,
                                message: "Please enter customer name",
                            },
                        ]}
                    >
                        <Input />
                    </Form.Item>

                    <Form.Item
                        name="email"
                        label="Email"
                        rules={[
                            { required: true, message: "Please enter email" },
                            {
                                type: "email",
                                message: "Please enter valid email",
                            },
                        ]}
                    >
                        <Input />
                    </Form.Item>

                    <Form.Item
                        name="phone"
                        label="Phone"
                        rules={[
                            {
                                required: true,
                                message: "Please enter phone number",
                            },
                        ]}
                    >
                        <Input />
                    </Form.Item>

                    <Form.Item name="address" label="Address">
                        <Input.TextArea rows={3} />
                    </Form.Item>

                    <Form.Item name="notes" label="Notes">
                        <Input.TextArea rows={3} />
                    </Form.Item>

                    <Form.Item>
                        <Space>
                            <Button
                                type="primary"
                                htmlType="submit"
                                loading={
                                    createMutation.isPending ||
                                    updateMutation.isPending
                                }
                            >
                                {editingCustomer ? "Update" : "Create"}
                            </Button>
                            <Button onClick={() => setIsModalVisible(false)}>
                                Cancel
                            </Button>
                        </Space>
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
};

export default CustomerList;
```

## Complete Dashboard Page

### `src/pages/Dashboard.js`

```javascript
import React from "react";
import { Row, Col, Card, Typography, Tabs, Spin } from "antd";
import { useQuery } from "@tanstack/react-query";
import { dashboardAPI } from "../services/api";
import DashboardStats from "../components/Dashboard/DashboardStats";
import UpcomingPaymentsTable from "../components/Dashboard/UpcomingPaymentsTable";
import OverduePaymentsTable from "../components/Dashboard/OverduePaymentsTable";

const { Title } = Typography;
const { TabPane } = Tabs;

const Dashboard = () => {
    const {
        data: dashboardData,
        isLoading,
        error,
    } = useQuery({
        queryKey: ["dashboard"],
        queryFn: dashboardAPI.analytics,
        refetchInterval: 30000, // Refetch every 30 seconds
    });

    const handleMarkPaid = (payment) => {
        // Implement mark as paid functionality
        console.log("Mark as paid:", payment);
    };

    const handleContact = (customer) => {
        // Implement contact customer functionality
        console.log("Contact customer:", customer);
    };

    if (isLoading) {
        return (
            <div style={{ textAlign: "center", padding: "50px" }}>
                <Spin size="large" />
            </div>
        );
    }

    if (error) {
        return (
            <div style={{ textAlign: "center", padding: "50px" }}>
                <Title level={3} type="danger">
                    Error loading dashboard data
                </Title>
            </div>
        );
    }

    return (
        <div style={{ padding: "24px" }}>
            <Title level={2}>Dashboard</Title>

            {/* Summary Statistics */}
            <DashboardStats data={dashboardData?.data} />

            {/* Detailed Tables */}
            <Row gutter={[16, 16]} style={{ marginTop: "24px" }}>
                <Col xs={24} lg={12}>
                    <Card title="Upcoming Payments" style={{ height: "500px" }}>
                        <UpcomingPaymentsTable
                            data={dashboardData?.data?.upcoming}
                            onMarkPaid={handleMarkPaid}
                        />
                    </Card>
                </Col>

                <Col xs={24} lg={12}>
                    <Card title="Overdue Payments" style={{ height: "500px" }}>
                        <OverduePaymentsTable
                            data={dashboardData?.data?.overduePayments}
                            onMarkPaid={handleMarkPaid}
                            onContact={handleContact}
                        />
                    </Card>
                </Col>
            </Row>

            {/* Additional Tabs */}
            <Card style={{ marginTop: "24px" }}>
                <Tabs defaultActiveKey="recent">
                    <TabPane tab="Recent Payments" key="recent">
                        {/* Recent payments table implementation */}
                    </TabPane>
                    <TabPane tab="Top Customers" key="customers">
                        {/* Top customers table implementation */}
                    </TabPane>
                    <TabPane tab="Monthly Trend" key="trend">
                        {/* Monthly trend chart implementation */}
                    </TabPane>
                </Tabs>
            </Card>
        </div>
    );
};

export default Dashboard;
```

## Error Handling

### `src/components/ErrorBoundary.js`

```javascript
import React from "react";
import { Result, Button } from "antd";

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, errorInfo) {
        console.error("Error caught by boundary:", error, errorInfo);
    }

    render() {
        if (this.state.hasError) {
            return (
                <Result
                    status="500"
                    title="500"
                    subTitle="Sorry, something went wrong."
                    extra={
                        <Button
                            type="primary"
                            onClick={() => window.location.reload()}
                        >
                            Reload Page
                        </Button>
                    }
                />
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
```

## App Setup

### `src/App.js`

```javascript
import React from "react";
import {
    BrowserRouter as Router,
    Routes,
    Route,
    Navigate,
} from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ConfigProvider } from "antd";
import { AuthProvider, useAuth } from "./contexts/AuthContext";
import ErrorBoundary from "./components/ErrorBoundary";
import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Customers from "./pages/Customers";
import Installments from "./pages/Installments";
import "antd/dist/reset.css";

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

const ProtectedRoute = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return <div>Loading...</div>;
    }

    return isAuthenticated ? children : <Navigate to="/login" />;
};

const AppRoutes = () => {
    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route
                path="/dashboard"
                element={
                    <ProtectedRoute>
                        <Dashboard />
                    </ProtectedRoute>
                }
            />
            <Route
                path="/customers"
                element={
                    <ProtectedRoute>
                        <Customers />
                    </ProtectedRoute>
                }
            />
            <Route
                path="/installments"
                element={
                    <ProtectedRoute>
                        <Installments />
                    </ProtectedRoute>
                }
            />
            <Route path="/" element={<Navigate to="/dashboard" />} />
        </Routes>
    );
};

function App() {
    return (
        <ErrorBoundary>
            <QueryClientProvider client={queryClient}>
                <ConfigProvider>
                    <AuthProvider>
                        <Router>
                            <AppRoutes />
                        </Router>
                    </AuthProvider>
                </ConfigProvider>
            </QueryClientProvider>
        </ErrorBoundary>
    );
}

export default App;
```

## Usage Examples

### Login Component

```javascript
import React, { useState } from "react";
import { Form, Input, Button, Card, message } from "antd";
import { useAuth } from "../contexts/AuthContext";
import { useNavigate } from "react-router-dom";

const Login = () => {
    const [loading, setLoading] = useState(false);
    const { login } = useAuth();
    const navigate = useNavigate();

    const onFinish = async (values) => {
        setLoading(true);
        const result = await login(values);
        setLoading(false);

        if (result.success) {
            message.success("Login successful");
            navigate("/dashboard");
        } else {
            message.error(result.error);
        }
    };

    return (
        <div
            style={{
                display: "flex",
                justifyContent: "center",
                alignItems: "center",
                minHeight: "100vh",
                background: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
            }}
        >
            <Card title="Login" style={{ width: 400 }}>
                <Form name="login" onFinish={onFinish} layout="vertical">
                    <Form.Item
                        name="email"
                        label="Email"
                        rules={[
                            {
                                required: true,
                                message: "Please input your email!",
                            },
                            {
                                type: "email",
                                message: "Please enter a valid email!",
                            },
                        ]}
                    >
                        <Input />
                    </Form.Item>

                    <Form.Item
                        name="password"
                        label="Password"
                        rules={[
                            {
                                required: true,
                                message: "Please input your password!",
                            },
                        ]}
                    >
                        <Input.Password />
                    </Form.Item>

                    <Form.Item>
                        <Button
                            type="primary"
                            htmlType="submit"
                            loading={loading}
                            block
                        >
                            Login
                        </Button>
                    </Form.Item>
                </Form>
            </Card>
        </div>
    );
};

export default Login;
```

This comprehensive React integration guide provides everything needed to build a full-featured frontend application with the Installment Manager API. The code includes proper error handling, loading states, and follows React best practices.
