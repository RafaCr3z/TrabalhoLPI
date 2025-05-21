<%@ page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@ page import="java.sql.*" %>

<%!
    // Método para obter conexão com o banco de dados
    public static Connection getConnection() throws Exception {
        String dbhost = "localhost";
        String dbuser = "root";
        String dbpass = "";
        String dbname = "FelixBus";
        
        Class.forName("com.mysql.jdbc.Driver");
        Connection conn = DriverManager.getConnection("jdbc:mysql://" + dbhost + "/" + dbname, dbuser, dbpass);
        
        if (conn == null) {
            throw new Exception("Falha técnica: Não foi possível conectar ao banco de dados");
        }
        
        return conn;
    }
%>